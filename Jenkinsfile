pipeline {
    agent any

    environment {
        DOCKER_IMAGE = "mrizkyardian/jagonugas-komwan"
        APP_PORT     = "8081"
        DB_PORT      = "3307"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Docker image') {
            steps {
                sh '''
                docker build -t ${DOCKER_IMAGE}:${BUILD_NUMBER} .
                docker tag ${DOCKER_IMAGE}:${BUILD_NUMBER} ${DOCKER_IMAGE}:latest
                '''
            }
        }

        stage('Push to Docker Hub') {
            environment {
                DOCKERHUB_CRED = credentials('dockerhub-credentials')  // buat di Jenkins
            }
            steps {
                sh '''
                echo "${DOCKERHUB_CRED_PSW}" | docker login -u "${DOCKERHUB_CRED_USR}" --password-stdin
                docker push ${DOCKER_IMAGE}:${BUILD_NUMBER}
                docker push ${DOCKER_IMAGE}:latest
                docker logout
                '''
            }
        }

        stage('Deploy with docker compose') {
            steps {
                sh '''
                export TAG=${BUILD_NUMBER}
                export APP_PORT=${APP_PORT}
                export DB_PORT=${DB_PORT}

                docker compose down
                docker compose pull || true
                docker compose up -d
                '''
            }
        }
    }

    post {
        always {
            sh 'docker image prune -f || true'
        }
    }
}
