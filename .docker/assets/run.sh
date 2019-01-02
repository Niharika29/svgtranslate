#!/bin/sh

npm ci
echo "npm ci finished"

tail -F /dev/null
