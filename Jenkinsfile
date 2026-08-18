pipeline {
    agent any
    
    environment {
        IMAGE_NAME = 'demo-shop' 
        TAG = "${env.BUILD_NUMBER}"
    }

    stages {
        stage('Build') {
            steps {
                sh "docker build -t ${IMAGE_NAME}:${TAG} . "
            }
        }

        stage('Test') {
            steps {
                sh "docker run --rm ${IMAGE_NAME}:${TAG} php -v"
            }
        }
    }
}