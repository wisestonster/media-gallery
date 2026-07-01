#!/bin/bash
php \
  -d upload_max_filesize=50M \
  -d post_max_size=100M \
  -S localhost:8000
