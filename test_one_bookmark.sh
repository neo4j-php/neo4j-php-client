#!/bin/bash

cd /home/pratiksha/dev/neo4j-php-client/testkit-backend

if [ ! -d testkit ]; then
    git clone https://github.com/neo4j-drivers/testkit.git
    (cd testkit && git checkout 5.0)
fi

cd testkit
if [ ! -d venv ]; then
    python3 -m venv venv
fi
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

echo "Running test_last_bookmark..."
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_last_bookmark

echo "Exit code: $?"

