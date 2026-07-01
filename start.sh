#!/bin/bash
php \
  -d upload_max_filesize=50M \
  -d post_max_size=100M \
  -S 0.0.0.0:8000 router.php
