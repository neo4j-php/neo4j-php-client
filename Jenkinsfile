pipeline {
    agent any

    environment {
        BRANCH_NAME = "${GIT_BRANCH.split("/").size() > 1 ? GIT_BRANCH.split("/")[1] : GIT_BRANCH}"
    }

    stages {
        stage('Pull') {
            steps {
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose.yml pull'
            }
        }
        stage('Build') {
            steps {
                sh 'docker build -t php-neo4j:static-analysis .'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose.yml build --parallel'
                sh 'docker-compose -p $BRANCH_NAME build'
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
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME down --volumes --remove-orphans'
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME up -d --force-recreate --remove-orphans'
                sh 'sleep 10' // Wait for the servers to complete booting
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME run client-80 php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME run client-74 php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME down --volumes'
            }
        }
        stage ('Coverage') {
            steps {
                sh 'docker-compose -p $BRANCH_NAME down --volumes --remove-orphans'
                sh 'docker-compose -p $BRANCH_NAME up -d --force-recreate --remove-orphans'
                sh 'docker-compose -p $BRANCH_NAME run client vendor/bin/phpunit -d memory_limit=1024M'
                sh 'docker-compose -p $BRANCH_NAME down'
            }
        }
        stage('Publish') {
            steps {
                sh 'cp /usr/bin/cc-test-reporter ./cc-test-reporter'
                sh 'docker-compose run client ./cc-test-reporter format-coverage out/phpunit/clover.xml --input-type clover --output out/cc-test-reporter/report.json'
                sh 'docker-compose run client ./cc-test-reporter sum-coverage -o out/cc-test-reporter/report.total.json -p 1 out/cc-test-reporter/report.json'
                sh 'docker-compose run client ./cc-test-reporter upload-coverage --input out/cc-test-reporter/report.json --id ec331dd009edca126a4c27f4921c129de840c8a117643348e3b75ec547661f28'
            }
        }
    }
}
