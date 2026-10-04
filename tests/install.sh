#!/usr/bin/env bash
set -euo pipefail
cd /workspace/mjstylepreview
docker pull wordpress@sha256:4abf7a450ee477dde967584f8174d7e03221d224c4971a0c38d84e7254426e64
docker pull mariadb@sha256:6422478cb8e159f080fb1d8ccf65101e26fe51385787fde7d16c3b165a331f15
python3 -m venv /workspace/tools/test-venv
/workspace/tools/test-venv/bin/pip install -r tests/requirements.txt
