#!/usr/bin/env bash
# Disposable LOCAL tests only. No live OpenAI requests; no production WordPress data.
set -euo pipefail
cd /workspace/mjstylepreview
wp_image='wordpress@sha256:4abf7a450ee477dde967584f8174d7e03221d224c4971a0c38d84e7254426e64'
db_image='mariadb@sha256:6422478cb8e159f080fb1d8ccf65101e26fe51385787fde7d16c3b165a331f15'
docker network inspect mjsp-test >/dev/null 2>&1 || docker network create mjsp-test
if docker container inspect mjsp-db >/dev/null 2>&1; then docker start mjsp-db >/dev/null; else
 docker run -d --name mjsp-db --network mjsp-test -e MARIADB_RANDOM_ROOT_PASSWORD=1 -e MARIADB_DATABASE=mjsp -e MARIADB_USER=mjsp -e MARIADB_PASSWORD=local-test-only "$db_image"
fi
if docker container inspect mjsp-wp >/dev/null 2>&1; then docker start mjsp-wp >/dev/null; else
 docker run -d --name mjsp-wp --network mjsp-test -p 127.0.0.1:8090:80 -e WORDPRESS_DB_HOST=mjsp-db -e WORDPRESS_DB_USER=mjsp -e WORDPRESS_DB_PASSWORD=local-test-only -e WORDPRESS_DB_NAME=mjsp -e 'WORDPRESS_CONFIG_EXTRA=$_SERVER["HTTPS"]="on"; define("DISABLE_WP_CRON",true);' "$wp_image"
fi
for attempt in {1..30}; do
 if docker exec mjsp-db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && docker exec mjsp-wp test -f /var/www/html/wp-config.php; then break; fi
 if [ "$attempt" = 30 ]; then exit 1; fi
 sleep 1
done
docker cp mj-style-preview/. mjsp-wp:/var/www/html/wp-content/plugins/mj-style-preview/
docker exec mjsp-wp chown -R www-data:www-data /var/www/html/wp-content/plugins/mj-style-preview
docker exec mjsp-wp mkdir -p /var/www/html/wp-content/mu-plugins
docker cp tests/mock-openai.php mjsp-wp:/var/www/html/wp-content/mu-plugins/mock-openai.php
docker exec mjsp-wp chmod 644 /var/www/html/wp-content/mu-plugins/mock-openai.php
for file in wp-integration render uninstall; do
 docker cp "tests/$file.php" "mjsp-wp:/tmp/mjsp-$file.php"
 docker exec mjsp-wp chmod 644 "/tmp/mjsp-$file.php"
done
docker exec mjsp-wp sh -c 'printf "upload_max_filesize=16M\npost_max_size=20M\nmemory_limit=256M\n" > /usr/local/etc/php/conf.d/mjsp-test.ini'
docker exec mjsp-wp apachectl graceful
docker exec -u www-data mjsp-wp php /tmp/mjsp-wp-integration.php
docker exec -u www-data mjsp-wp php /tmp/mjsp-render.php > tests/fixture.html

/workspace/tools/test-venv/bin/python - <<'PYTHON'
from PIL import Image
Image.new('RGB', (600, 800), (110, 83, 70)).save('/tmp/mjsp-test.jpg')
PYTHON
