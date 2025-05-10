pipeline {
    agent any
    stages {
        stage('Checkout') {
            steps {
                git branch: 'main', url: 'https://github.com/issamkhadiri1989/ldap_implementation.git'
            }
        }
        stage('Deploy with Ansible') {
            steps {
                ansiblePlaybook(
                    playbook: 'deploy.yml',
                    inventory: 'inventory.ini',
                    credentialsId: 'f0049021-7349-4c3c-bf03-e8c3dabd8ee1'
                )
            }
        }
    }
}