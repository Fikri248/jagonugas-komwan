pipeline {
    agent any

    environment {
        DOCKER_IMAGE = "mrizkyardian/jagonugas-komwan"
        APP_PORT     = "8081"
        DB_PORT      = "3307"
        REGISTRY_CREDENTIALS = 'dockerhub-credentials'   // ID credentials di Jenkins
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Docker image') {
            steps {
                script {
                    if (isUnix()) {
                        sh """
                          docker build -t ${DOCKER_IMAGE}:${BUILD_NUMBER} .
                          docker tag ${DOCKER_IMAGE}:${BUILD_NUMBER} ${DOCKER_IMAGE}:latest
                        """
                    } else {
                        bat """
                          docker build -t ${DOCKER_IMAGE}:${BUILD_NUMBER} .
                          docker tag ${DOCKER_IMAGE}:${BUILD_NUMBER} ${DOCKER_IMAGE}:latest
                        """
                    }
                }
            }
        }

        stage('Push to Docker Hub') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: REGISTRY_CREDENTIALS,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    script {
                        if (isUnix()) {
                            sh '''
                              echo "$DOCKER_PASS" | docker login -u "$DOCKER_USER" --password-stdin
                              docker push ${DOCKER_IMAGE}:${BUILD_NUMBER}
                              docker push ${DOCKER_IMAGE}:latest
                              docker logout
                            '''
                        } else {
                            bat """
                              echo %DOCKER_PASS% | docker login -u %DOCKER_USER% --password-stdin
                              docker push %DOCKER_IMAGE%:%BUILD_NUMBER%
                              docker push %DOCKER_IMAGE%:latest
                              docker logout
                            """
                        }
                    }
                }
            }
        }

        stage('Deploy with docker compose') {
            steps {
                script {
                    if (isUnix()) {
                        sh '''
                          export TAG=${BUILD_NUMBER}
                          export APP_PORT=${APP_PORT}
                          export DB_PORT=${DB_PORT}

                          docker compose down
                          docker compose pull || true
                          docker compose up -d
                        '''
                    } else {
                        bat """
                          set TAG=${BUILD_NUMBER}
                          set APP_PORT=${APP_PORT}
                          set DB_PORT=${DB_PORT}

                          docker compose down
                          docker compose pull || true
                          docker compose up -d
                        """
                    }
                }
            }
        }
    }

    post {
        always {
            script {
                if (isUnix()) {
                    sh 'docker image prune -f || true'
                } else {
                    bat 'docker image prune -f || true'
                }
            }
        }
    }
}
