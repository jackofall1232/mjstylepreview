const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
 const browser = await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 let checks=0;
 const check=(value,label)=>{if(!value)throw new Error(label);console.log('PASS: '+label);checks++;};
 for(const width of [375,390,768,1280]) {
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://example.test/wp-admin/admin-ajax.php',async route=>{
   const body=route.request().postData()||'';
   const data=body.includes('mjsp_session') ? {nonce:'test-nonce',maxBytes:10*1024*1024} : {image:fs.readFileSync('/tmp/mjsp-test.jpg').toString('base64'),applied:'long, layers, blonde highlights',lifetime:600};
   await route.fulfill({status:200,contentType:'application/json',headers:{'Access-Control-Allow-Origin':'http://127.0.0.1:8091','Access-Control-Allow-Credentials':'true'},body:JSON.stringify({success:true,data})});
  });
  await page.goto('http://127.0.0.1:8091/tests/fixture.html');
  check(await page.locator('input[name=consent]').isChecked()===false,`${width}: consent starts unchecked`);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${width}: no horizontal overflow`);
  await page.locator('input[type=file]').setInputFiles('/tmp/mjsp-test.jpg');
  await page.locator('textarea').fill('long layers and blonde highlights');
  await page.locator('input[name=consent]').check();
  await page.locator('button[type=submit]').click();
  await page.locator('.mjsp__result').waitFor({state:'visible'});
  check(await page.locator('.mjsp__generated').evaluate(el=>el.complete&&el.naturalWidth>0),`${width}: generated image displays`);
  check(await page.locator('.mjsp__result').evaluate(el=>el===document.activeElement),`${width}: result receives focus`);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${width}: result fits viewport`);
  check(await page.locator('.mjsp__primary').evaluate(el=>el.getBoundingClientRect().height>=44),`${width}: touch target size`);
  check(await page.locator('.mjsp__primary').evaluate(el=>getComputedStyle(el).animationName==='none'),`${width}: reduced motion`);
  await page.screenshot({path:`/tmp/mjsp-${width}.png`,fullPage:true});
  await page.locator('.mjsp__again').click();
  check(await page.locator('textarea').evaluate(el=>el===document.activeElement),`${width}: retry focuses hairstyle input`);
  check(errors.length===0,`${width}: no JavaScript runtime errors`);
  await page.close();
 }
 await browser.close();console.log(`${checks} browser checks passed`);
})().catch(e=>{console.error(e);process.exit(1)});
