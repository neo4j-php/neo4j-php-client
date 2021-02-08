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
                sh 'docker build -t php-neo4j:static-analysis-$BRANCH_NAME .'
                sh 'docker-compose -p $BRANCH_NAME -f docker/docker-compose.yml build --parallel'
                sh 'docker-compose -p $BRANCH_NAME build'
            }
        }
        stage('Static Analysis') {
            steps {
                sh 'docker run php-neo4j:static-analysis-$BRANCH_NAME tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --dry-run'
                sh 'docker run php-neo4j:static-analysis-$BRANCH_NAME tools/psalm/vendor/bin/psalm --show-info=true'
            }
        }
        stage('Test') {
            steps {
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME down --volumes --remove-orphans'
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME up -d --force-recreate --remove-orphans'
                sh 'sleep 30' // Wait for the servers to complete booting
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME run client-80 php vendor/bin/phpunit'
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME run client-74 php vendor/bin/phpunit'
            }
        }
        stage ('Coverage') {
            steps {
                sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME run client bash -c "\
                    git checkout -B $BRANCH_NAME && \
                    cc-test-reporter before-build && \
                    vendor/bin/phpunit --config phpunit.coverage.xml.dist -d memory_limit=1024M && \
                    cp out/phpunit/clover.xml clover.xml && \
                    cc-test-reporter after-build --id ec331dd009edca126a4c27f4921c129de840c8a117643348e3b75ec547661f28 --exit-code 0"'
            }
        }
    }

    post {
        always {
            sh 'docker-compose -f docker/docker-compose.yml -p $BRANCH_NAME down --volumes'
        }
    }
}
