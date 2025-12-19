pipeline {
  agent any

  environment {
    IMAGE_NAME = 'mrizkyardian/jago-nugas'          // repo Docker Hub kamu
    REGISTRY_CREDENTIALS = 'dockerhub-credentials'  // ID credentials di Jenkins
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
      }
    }

    stage('Build Docker Image') {
      steps {
        sh "docker build -t ${env.IMAGE_NAME}:${env.BUILD_NUMBER} ."
      }
    }

    stage('Push Docker Image') {
      steps {
        withCredentials([usernamePassword(
          credentialsId: env.REGISTRY_CREDENTIALS,
          usernameVariable: 'DOCKER_USER',
          passwordVariable: 'DOCKER_PASS'
        )]) {
          sh '''
            echo "$DOCKER_PASS" | docker login -u "$DOCKER_USER" --password-stdin
            docker push ${IMAGE_NAME}:${BUILD_NUMBER}
            docker tag ${IMAGE_NAME}:${BUILD_NUMBER} ${IMAGE_NAME}:latest
            docker push ${IMAGE_NAME}:latest
          '''
        }
      }
    }
  }

  post {
    success {
      echo "Image ${env.IMAGE_NAME}:${env.BUILD_NUMBER} berhasil dipush, siap dipakai di Azure Web App / Container Apps."
    }
  }
}
