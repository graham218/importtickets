/**
 * Ticket Import plugin JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize plugin functionality
    initImportTickets();
});

function initImportTickets() {
    // Add event listeners for file input validation
    const fileInput = document.getElementById('import_file');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            validateFile(this);
        });
    }
    
    console.log('Ticket Import plugin JavaScript loaded');
}

function validateFile(fileInput) {
    const file = fileInput.files[0];
    if (!file) return;
    
    // Check file size (10MB max)
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('File size exceeds maximum allowed size (10MB)');
        fileInput.value = '';
        return;
    }
    
    // Basic file type validation
    const fileName = file.name.toLowerCase();
    if (!fileName.endsWith('.csv') && !fileName.endsWith('.txt')) {
        alert('Please select a CSV or text file');
        fileInput.value = '';
    }
}