pipeline {
    agent any

    environment {
        APP_ENV = 'testing'
        DB_CONNECTION = 'mysql'
        DB_HOST = '127.0.0.1'
        DB_PORT = '3306'
        DB_DATABASE = 'test_db'
        DB_USERNAME = 'root'
        DB_PASSWORD = 'password'
    }

    stages {
        stage('Backend - Dependencies') {
            steps {
                sh 'composer install --no-progress --prefer-dist'
            }
        }

        stage('Backend - Setup') {
            steps {
                sh 'cp .env.example .env'
                sh 'php artisan key:generate'
                sh 'chmod -R 775 storage bootstrap/cache'
            }
        }

        stage('Backend - Migrations') {
            steps {
                sh 'php artisan migrate --force'
            }
        }

        stage('Backend - Tests') {
            steps {
                sh 'php artisan test'
            }
        }

        stage('Backend - Code Style') {
            steps {
                sh './vendor/bin/pint --test --preset laravel'
            }
        }

        stage('Frontend - Dependencies') {
            steps {
                sh 'npm ci'
            }
        }

        stage('Frontend - Build') {
            steps {
                sh 'npm run build'
            }
        }
    }

    post {
        always {
            echo 'Pipeline finished'
        }
        success {
            echo 'Build succeeded!'
        }
        failure {
            echo 'Build failed!'
        }
    }
}
