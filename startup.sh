#!/bin/bash

# Copy custom nginx config
cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-available/default

# Reload nginx
service nginx reload
