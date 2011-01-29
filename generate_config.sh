#!/bin/bash

DOCUMENT_ROOT='/srv/nginx/html/cloud_jp_oracle_com'

. /u01/racovm/params.ini
. /u01/racovm/netconfig.ini

cat ${DOCUMENT_ROOT}/config.template.php | sed -e "s/%%DB_PASSWORD%%/$RACPASSWORD/g" -e "s/%%ASM_PASSWORD%%/$GRIDPASSWORD/g" -e "s?%%DB_SERVICE%%?${SIDNAME}/${RACCLUSTERNAME}?g" > ${DOCUMENT_ROOT}/config.php
