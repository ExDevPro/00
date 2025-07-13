/**
 * Free SMTP Tester - Frontend JavaScript
 * Handles all client-side functionality including form validation, 
 * SMTP testing, file uploads, and UI interactions
 */

class SMTPTester {
    constructor() {
        this.form = document.getElementById('smtp-form');
        this.logContainer = document.getElementById('log-container');
        this.logMessages = document.getElementById('log-messages');
        this.fileInput = document.getElementById('email-attachments');
        this.uploadedFiles = [];
        this.maxFileSize = 1048576; // 1MB
        this.allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'zip', 'rar', '7z'];
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupWYSIWYG();
        this.setupFileUpload();
        this.setupNavigation();
        this.loadAdminMessage();
        this.loadContent();
        this.updateSubjectPlaceholder();
    }

    setupEventListeners() {
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        
        // Test connection button
        document.getElementById('test-connection').addEventListener('click', () => this.testConnection());
        
        // SMTP host change to update subject placeholder
        document.getElementById('smtp-host').addEventListener('input', () => this.updateSubjectPlaceholder());
        
        // Log toggle
        document.getElementById('log-toggle').addEventListener('click', () => this.toggleLog());
        
        // Admin message close
        const messageClose = document.getElementById('message-close');
        if (messageClose) {
            messageClose.addEventListener('click', () => this.closeAdminMessage());
        }

        // Real-time validation
        const inputs = this.form.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }

    setupNavigation() {
        // Mobile menu toggle
        const navToggle = document.getElementById('nav-toggle');
        const navMenu = document.getElementById('nav-menu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Update active nav link on scroll
        window.addEventListener('scroll', () => this.updateActiveNavLink());
    }

    setupWYSIWYG() {
        const toolbar = document.querySelector('.wysiwyg-toolbar');
        const editor = document.getElementById('email-message');
        
        if (toolbar && editor) {
            toolbar.addEventListener('click', (e) => {
                e.preventDefault();
                const button = e.target.closest('.toolbar-btn');
                if (button) {
                    const command = button.dataset.command;
                    this.executeWYSIWYGCommand(command, button);
                }
            });

            // Update toolbar state on selection change
            editor.addEventListener('mouseup', () => this.updateToolbarState());
            editor.addEventListener('keyup', () => this.updateToolbarState());
        }
    }

    executeWYSIWYGCommand(command, button) {
        document.execCommand(command, false, null);
        
        // Toggle active state for formatting buttons
        if (['bold', 'italic', 'underline'].includes(command)) {
            button.classList.toggle('active');
        } else {
            // Remove active state from alignment buttons
            document.querySelectorAll('.toolbar-btn').forEach(btn => {
                if (btn.dataset.command && btn.dataset.command.startsWith('justify')) {
                    btn.classList.remove('active');
                }
            });
            button.classList.add('active');
        }
    }

    updateToolbarState() {
        const commands = ['bold', 'italic', 'underline'];
        commands.forEach(command => {
            const button = document.querySelector(`[data-command="${command}"]`);
            if (button) {
                if (document.queryCommandState(command)) {
                    button.classList.add('active');
                } else {
                    button.classList.remove('active');
                }
            }
        });
    }

    setupFileUpload() {
        const fileLabel = document.querySelector('.file-upload-label');
        const uploadedFilesContainer = document.getElementById('uploaded-files');

        // File input change
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));

        // Drag and drop
        fileLabel.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileLabel.classList.add('drag-over');
        });

        fileLabel.addEventListener('dragleave', () => {
            fileLabel.classList.remove('drag-over');
        });

        fileLabel.addEventListener('drop', (e) => {
            e.preventDefault();
            fileLabel.classList.remove('drag-over');
            this.handleFileSelect({ target: { files: e.dataTransfer.files } });
        });
    }

    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        const uploadedFilesContainer = document.getElementById('uploaded-files');

        files.forEach(file => {
            if (this.validateFile(file)) {
                this.uploadedFiles.push(file);
                this.displayUploadedFile(file, uploadedFilesContainer);
            }
        });

        // Clear the input so the same file can be selected again
        this.fileInput.value = '';
    }

    validateFile(file) {
        // Check file size
        if (file.size > this.maxFileSize) {
            this.showError(`File "${file.name}" is too large. Maximum size is 1MB.`);
            return false;
        }

        // Check file type
        const extension = file.name.split('.').pop().toLowerCase();
        if (!this.allowedTypes.includes(extension)) {
            this.showError(`File type "${extension}" is not allowed.`);
            return false;
        }

        // Check total size
        const totalSize = this.uploadedFiles.reduce((sum, f) => sum + f.size, 0) + file.size;
        if (totalSize > this.maxFileSize) {
            this.showError('Total file size exceeds 1MB limit.');
            return false;
        }

        return true;
    }

    displayUploadedFile(file, container) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div>
                <div class="file-name">${file.name}</div>
                <div class="file-size">${this.formatFileSize(file.size)}</div>
            </div>
            <button type="button" class="file-remove" data-file-name="${file.name}">
                <i class="fas fa-times"></i>
            </button>
        `;

        fileItem.querySelector('.file-remove').addEventListener('click', () => {
            this.removeFile(file.name);
            fileItem.remove();
        });

        container.appendChild(fileItem);
    }

    removeFile(fileName) {
        this.uploadedFiles = this.uploadedFiles.filter(file => file.name !== fileName);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    updateSubjectPlaceholder() {
        const smtpHost = document.getElementById('smtp-host').value;
        const subjectInput = document.getElementById('email-subject');
        
        if (smtpHost && subjectInput.value === subjectInput.defaultValue) {
            subjectInput.value = `Test Email from ${smtpHost}`;
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            return;
        }

        this.showLog();
        this.setButtonLoading('send-email', true);
        this.logMessage('Starting email send process...', 'info');

        try {
            const formData = this.prepareFormData();
            const response = await this.sendRequest('/api/smtp-test.php', formData);
            
            if (response.success) {
                this.logMessage('Email sent successfully!', 'success');
                this.showSuccess('Test email sent successfully!');
                this.form.reset();
                this.uploadedFiles = [];
                document.getElementById('uploaded-files').innerHTML = '';
            } else {
                this.logMessage(`Error: ${response.message}`, 'error');
                this.showError(response.message);
            }
        } catch (error) {
            this.logMessage(`Request failed: ${error.message}`, 'error');
            this.showError('Failed to send email. Please try again.');
        } finally {
            this.setButtonLoading('send-email', false);
        }
    }

    async testConnection() {
        if (!this.validateConnectionFields()) {
            return;
        }

        this.showLog();
        this.setButtonLoading('test-connection', true);
        this.logMessage('Testing SMTP connection...', 'info');

        try {
            const formData = this.prepareConnectionData();
            const response = await this.sendRequest('/api/smtp-test.php?action=test', formData);
            
            if (response.success) {
                this.logMessage('Connection test successful!', 'success');
                this.showSuccess('SMTP connection is working correctly!');
            } else {
                this.logMessage(`Connection failed: ${response.message}`, 'error');
                this.showError(response.message);
            }
        } catch (error) {
            this.logMessage(`Connection test failed: ${error.message}`, 'error');
            this.showError('Connection test failed. Please check your settings.');
        } finally {
            this.setButtonLoading('test-connection', false);
        }
    }

    prepareFormData() {
        const formData = new FormData();
        const formFields = new FormData(this.form);

        // Add form fields
        for (let [key, value] of formFields.entries()) {
            formData.append(key, value);
        }

        // Add WYSIWYG content
        const messageContent = document.getElementById('email-message').innerHTML;
        formData.append('email_message', messageContent);

        // Add files
        this.uploadedFiles.forEach((file, index) => {
            formData.append(`attachments[${index}]`, file);
        });

        return formData;
    }

    prepareConnectionData() {
        const formData = new FormData();
        const requiredFields = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_auth'];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field.replace('_', '-'));
            if (element) {
                formData.append(field, element.value);
            }
        });

        return formData;
    }

    async sendRequest(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            body: data
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    validateForm() {
        const requiredFields = [
            'smtp-host', 'smtp-port', 'smtp-username', 'smtp-password',
            'from-email', 'recipient-email'
        ];

        let isValid = true;

        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateConnectionFields() {
        const requiredFields = ['smtp-host', 'smtp-port', 'smtp-username', 'smtp-password'];
        let isValid = true;

        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // Required field validation
        if (field.hasAttribute('required') && !value) {
            errorMessage = 'This field is required.';
            isValid = false;
        }

        // Email validation
        if (field.type === 'email' && value && !this.isValidEmail(value)) {
            errorMessage = 'Please enter a valid email address.';
            isValid = false;
        }

        // Port validation
        if (field.id === 'smtp-port' && value && !this.isValidPort(value)) {
            errorMessage = 'Please enter a valid port number.';
            isValid = false;
        }

        // Host validation
        if (field.id === 'smtp-host' && value && !this.isValidHost(value)) {
            errorMessage = 'Please enter a valid hostname or IP address.';
            isValid = false;
        }

        if (isValid) {
            this.clearFieldError(field);
        } else {
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPort(port) {
        const portNum = parseInt(port);
        return portNum > 0 && portNum <= 65535;
    }

    isValidHost(host) {
        // Basic hostname/IP validation
        const hostRegex = /^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/;
        const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        return hostRegex.test(host) || ipRegex.test(host);
    }

    showFieldError(field, message) {
        const errorElement = document.getElementById(`${field.id}-error`);
        if (errorElement) {
            errorElement.textContent = message;
            field.classList.add('error');
        }
    }

    clearFieldError(field) {
        const errorElement = document.getElementById(`${field.id}-error`);
        if (errorElement) {
            errorElement.textContent = '';
            field.classList.remove('error');
        }
    }

    setButtonLoading(buttonId, loading) {
        const button = document.getElementById(buttonId);
        if (button) {
            if (loading) {
                button.classList.add('loading');
                button.disabled = true;
            } else {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }
    }

    showLog() {
        this.logContainer.style.display = 'block';
        this.logContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    hideLog() {
        this.logContainer.style.display = 'none';
    }

    toggleLog() {
        const content = document.querySelector('.log-content');
        const toggle = document.getElementById('log-toggle');
        const icon = toggle.querySelector('i');
        
        if (content.style.display === 'none' || !content.style.display) {
            content.style.display = 'block';
            icon.className = 'fas fa-chevron-up';
        } else {
            content.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
        }
    }

    logMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry log-${type}`;
        logEntry.innerHTML = `
            <span class="log-timestamp">[${timestamp}]</span>
            <span class="log-message">${message}</span>
        `;
        this.logMessages.appendChild(logEntry);
        this.logMessages.scrollTop = this.logMessages.scrollHeight;
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button class="notification-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: ${type === 'success' ? 'var(--success-color)' : 'var(--error-color)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        `;

        // Add close functionality
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });

        // Add to DOM
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    updateActiveNavLink() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
        
        let currentSection = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (window.scrollY >= sectionTop - 200) {
                currentSection = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${currentSection}`) {
                link.classList.add('active');
            }
        });
    }

    closeAdminMessage() {
        const adminMessage = document.getElementById('admin-message');
        if (adminMessage) {
            adminMessage.style.display = 'none';
        }
    }

    async loadAdminMessage() {
        try {
            const response = await fetch('/api/admin-message.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.message) {
                    const messageText = document.getElementById('admin-message-text');
                    const messageIcon = document.querySelector('.message-icon');
                    
                    if (messageText) {
                        messageText.textContent = data.message.content;
                    }
                    
                    if (messageIcon && data.message.type) {
                        const iconClass = {
                            'info': 'fa-info-circle',
                            'warning': 'fa-exclamation-triangle',
                            'success': 'fa-check-circle',
                            'error': 'fa-times-circle'
                        }[data.message.type] || 'fa-info-circle';
                        
                        messageIcon.className = `message-icon fas ${iconClass}`;
                    }
                }
            }
        } catch (error) {
            console.log('Could not load admin message');
        }
    }

    async loadContent() {
        try {
            const response = await fetch('/content/sections.html');
            if (response.ok) {
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Load different sections
                const technicalArticle = doc.getElementById('technical-article');
                const howToGuide = doc.getElementById('how-to-guide');
                const faqSection = doc.getElementById('faq-section');

                if (technicalArticle) {
                    document.getElementById('technical-article-content').innerHTML = technicalArticle.innerHTML;
                }

                if (howToGuide) {
                    document.getElementById('how-to-guide-content').innerHTML = howToGuide.innerHTML;
                }

                if (faqSection) {
                    document.getElementById('faq-content').innerHTML = faqSection.innerHTML;
                    this.setupFAQ();
                }
            }
        } catch (error) {
            console.log('Could not load content sections');
        }
    }

    setupFAQ() {
        const faqQuestions = document.querySelectorAll('.faq-question');
        faqQuestions.forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const isActive = question.classList.contains('active');

                // Close all FAQ items
                faqQuestions.forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.remove('active');
                });

                // Open clicked item if it wasn't active
                if (!isActive) {
                    question.classList.add('active');
                    answer.classList.add('active');
                }
            });
        });
    }

    setupShareButtons() {
        const shareButtons = document.querySelectorAll('.share-btn');
        shareButtons.forEach(button => {
            button.addEventListener('click', () => {
                const platform = button.dataset.share;
                const url = encodeURIComponent(window.location.href);
                const title = encodeURIComponent(document.title);
                
                let shareUrl = '';
                if (platform === 'twitter') {
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                } else if (platform === 'linkedin') {
                    shareUrl = `https://linkedin.com/sharing/share-offsite/?url=${url}`;
                }
                
                if (shareUrl) {
                    window.open(shareUrl, '_blank', 'width=600,height=400');
                }
            });
        });
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new SMTPTester();
});

// Add notification styles
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: currentColor;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: var(--radius);
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    
    .notification-close:hover {
        opacity: 1;
    }
`;
document.head.appendChild(notificationStyles);
