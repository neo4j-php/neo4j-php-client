pipeline {
    agent any

    environment {
        BRANCH_NAME = "${GIT_BRANCH.split("/").size() > 1 ? GIT_BRANCH.split("/")[1] : GIT_BRANCH}"
    }

    stages {
        stage('Build') {
            steps {
                sh 'docker build -t php-neo4j:static-analysis .'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose-4.2.yml build'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose-4.1.yml build'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose-4.0.yml build'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose-3.5.yml build'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose-2.3.yml build'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose-php-7.4.yml build'
                sh 'docker-compose build'
                sh 'docker build -t php-neo4j:static-analysis .'
            }
        }
        stage('Static Analysis') {
            steps {
                sh 'docker run php-neo4j:static-analysis tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --dry-run'
                sh 'docker run php-neo4j:static-analysis tools/psalm/vendor/bin/psalm --show-info=true'
            }
        }
        stage('Test') {
            steps {
                sh 'docker-compose -f docker/docker-compose-4.2.yml -p $BRANCH_NAME down --volumes'
                sh 'docker-compose -f docker/docker-compose-4.2.yml -p $BRANCH_NAME up -d --force-recreate'
                sh 'docker-compose -f docker/docker-compose-4.2.yml -p $BRANCH_NAME run client php vendor/bin/phpunit -d memory_limit=1024M'
                sh 'docker-compose -f docker/docker-compose-4.2.yml -p $BRANCH_NAME down'


                sh 'docker-compose -f docker/docker-compose-4.1.yml -p $BRANCH_NAME down --volumes'
                sh 'docker-compose -f docker/docker-compose-4.1.yml -p $BRANCH_NAME up -d --force-recreate'
                sh 'docker-compose -f docker/docker-compose-4.1.yml -p $BRANCH_NAME run client php vendor/bin/phpunit -d memory_limit=1024M'
                sh 'docker-compose -f docker/docker-compose-4.1.yml -p $BRANCH_NAME down'


                sh 'docker-compose -f docker/docker-compose-4.0.yml -p $BRANCH_NAME down --volumes'
                sh 'docker-compose -f docker/docker-compose-4.0.yml -p $BRANCH_NAME up -d --force-recreate'
                sh 'docker-compose -f docker/docker-compose-4.0.yml -p $BRANCH_NAME run client php vendor/bin/phpunit -d memory_limit=1024M'
                sh 'docker-compose -f docker/docker-compose-4.0.yml -p $BRANCH_NAME down'


                sh 'docker-compose -f docker/docker-compose-3.5.yml -p $BRANCH_NAME down --volumes'
                sh 'docker-compose -f docker/docker-compose-3.5.yml -p $BRANCH_NAME up -d --force-recreate'
                sh 'docker-compose -f docker/docker-compose-3.5.yml -p $BRANCH_NAME run client php vendor/bin/phpunit -d memory_limit=1024M'
                sh 'docker-compose -f docker/docker-compose-3.5.yml -p $BRANCH_NAME down'

//                 sh 'docker-compose -f docker/docker-compose-2.3.yml run client php vendor/bin/phpunit'
                sh 'docker-compose -p $BRANCH_NAME down'
                sh 'docker-compose -p $BRANCH_NAME run client vendor/bin/phpunit -d memory_limit=1024M'
                sh 'docker-compose -p $BRANCH_NAME down'
            }
        }
        stage('Publish') {
            steps {
                sh 'cp /usr/bin/cc-test-reporter ./cc-test-reporter'
                sh 'docker-compose run client ./cc-test-reporter format-coverage out/phpunit/clover.xml --input-type clover --output out/cc-test-reporter/report.json'
                sh 'docker-compose run client ./cc-test-reporter sum-coverage -o out/cc-test-reporter/report.total.json -p 1 out/cc-test-reporter/report.json '
                sh 'docker-compose run client ./cc-test-reporter upload-coverage --input out/cc-test-reporter/report.json --id ec331dd009edca126a4c27f4921c129de840c8a117643348e3b75ec547661f28'
            }
        }
    }
}
