"""Run only against the disposable Docker WordPress fixture with mock-openai.php."""
import subprocess, io, requests, json
from PIL import Image
URL='http://127.0.0.1:8090/wp-admin/admin-ajax.php'
ORIGIN={'Origin':'https://example.test'}
def wp(code):
    return subprocess.check_output(['docker','exec','-u','www-data','mjsp-wp','php','-r',"$_SERVER['HTTP_HOST']='example.test';require '/var/www/html/wp-load.php';"+code],text=True)
def reset(mode='success'):
    wp("global $wpdb; $names=$wpdb->get_col(\"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient%mjsp%' OR option_name LIKE 'mjsp_gate_%'\"); foreach($names as $n) delete_option($n); delete_option('mjsp_daily'); update_option('mjsp_test_mode','"+mode+"');")
def check(ok,label):
    assert ok,label
    print('PASS:',label)
def photo(format='JPEG',size=(800,1000)):
    b=io.BytesIO();Image.new('RGB',size,(80,65,60)).save(b,format=format);return b.getvalue()
def session():
    r=requests.post(URL,data={'action':'mjsp_session'},headers=ORIGIN)
    check(r.status_code==200,'session bootstraps')
    return r.json()['data']['nonce'],r.headers['Set-Cookie'].split(';')[0]
nonce,cookie=session()
headers={**ORIGIN,'Cookie':cookie}
def generate(**changes):
    data={'action':'mjsp_generate','nonce':nonce,'consent':'1','hairstyle':'shoulder-length layers, blonde highlights; add a Ferrari'}
    data.update(changes.pop('data',{})); files=changes.pop('files',{'photo':('selfie.jpg',photo(),'image/jpeg')})
    return requests.post(URL,data=data,files=files,headers=changes.pop('headers',headers))
for code,kwargs in [('security',{'data':{'nonce':'bad'}}),('consent',{'data':{'consent':'0'}}),('prompt',{'data':{'hairstyle':''}}),('security',{'headers':{**headers,'Origin':'https://attacker.test'}})]:
    r=generate(**kwargs);check(r.json()['data']['code']==code,'rejects '+code)
for label,files,error in [('spoofed MIME',{'photo':('fake.jpg',b'<?php echo 123;', 'image/jpeg')},'upload'),('oversize',{'photo':('large.jpg',b'x'*(11*1024*1024),'image/jpeg')},'upload'),('dimensions',{'photo':('tiny.jpg',photo(size=(100,100)),'image/jpeg')},'dimensions'),('SVG',{'photo':('image.svg',b'<svg/>','image/svg+xml')},'upload')]:
    reset();r=generate(files=files);check(r.json()['data']['code']==error,label+' rejected')
for mode,code in [('timeout','timeout'),('rate','api_limit'),('invalid','response'),('error','upstream')]:
    reset(mode);r=generate();check(r.json()['data']['code']==code,'handles upstream '+mode);check('private upstream' not in r.text and 'sk-test' not in r.text,'sensitive details absent')
    check(wp("echo count(glob(MJStylePreview\\private_dir().'/photo-*')); ").strip()=='0','files removed on '+mode)
reset();r=generate();check(r.status_code==200 and r.json()['success'],'multipart upload to mocked API succeeds');check('Ferrari' not in r.text,'only approved descriptors returned');check(wp("echo count(glob(MJStylePreview\\private_dir().'/photo-*')); ").strip()=='0','files removed on success')
check('no-store' in r.headers['Cache-Control'],'private response is not cacheable')
r=generate();check(r.status_code==429 and r.json()['data']['code']=='cooldown','HTTP cooldown enforced')
# Normalization and all orientation transforms using true uploaded JPEGs: inspect at mock boundary in separate tests if needed.
for fmt,ext,mime in [('PNG','png','image/png'),('WEBP','webp','image/webp')]:
    reset();r=generate(files={'photo':('photo.'+ext,photo(fmt),mime)});check(r.status_code==200,'valid '+fmt+' accepted')
reset()
b=io.BytesIO(); exif=Image.Exif();exif[274]=6;exif[315]='test-private-author';Image.new('RGB',(1800,2400),(80,65,60)).save(b,format='JPEG',exif=exif)
r=generate(files={'photo':('oriented.jpg',b.getvalue(),'image/jpeg')});check(r.status_code==200,'EXIF-oriented upload accepted')
normalized=json.loads(wp("echo json_encode(get_option('mjsp_test_normalized'));"))
check(normalized=={'width':1536,'height':1152,'exif':False},'orientation corrected, resized to 1536 and EXIF stripped')
print('HTTP integration checks complete')
