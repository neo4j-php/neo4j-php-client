pipeline {
    agent any

    stages {
        stage('Build') {
            steps {
                sh 'docker build -t php-neo4j:static-analysis .'
                sh 'docker-compose -f docker/docker-compose-4.2.yml build'
                sh 'docker-compose -f docker/docker-compose-4.1.yml build'
                sh 'docker-compose -f docker/docker-compose-4.0.yml build'
                sh 'docker-compose -f docker/docker-compose-3.5.yml build'
                sh 'docker-compose -f docker/docker-compose-2.3.yml build'
                sh 'docker-compose -f docker/docker-compose-php-7.4.yml build'
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
                sh 'docker-compose -f docker/docker-compose-4.2.yml run client php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose-4.1.yml run client php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose-4.0.yml run client php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose-3.5.yml run client php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose-2.3.yml run client php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose-php-7.4.yml run client php vendor/bin/phpunit'
            }
        }
        stage('Deploy') {
            steps {
                echo 'Deploying....'
            }
        }
    }
}
