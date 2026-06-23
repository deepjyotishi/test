// ============================================
// VALIDATION FUNCTIONS
// ============================================

const validateEmail = (email) => {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
};

const validatePassword = (password) => {
  return password.length >= 8;
};

const validateForm = (formData) => {
  const errors = [];
  if (!formData.get('email') || !validateEmail(formData.get('email'))) {
    errors.push('Invalid email address');
  }
  if (!formData.get('password') || !validatePassword(formData.get('password'))) {
    errors.push('Password must be at least 8 characters');
  }
  return errors;
};

// ============================================
// FORM SUBMISSION WITH FETCH
// ============================================

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      
      // Validate form if it has email/password fields
      if (formData.get('email') && formData.get('password')) {
        const errors = validateForm(formData);
        if (errors.length > 0) {
          alert('Validation errors:\n' + errors.join('\n'));
          return;
        }
      }
      
      try {
        const response = await fetch(form.action, {
          method: form.method || 'POST',
          body: formData
        });
        const data = await response.json();
        
        if (data.success) {
          window.location.href = data.redirect || 'dashboard.php';
        } else {
          alert(data.message || 'An error occurred');
        }
      } catch (error) {
        console.error('Form submission error:', error);
        alert('An error occurred. Please try again.');
      }
    });
  }
});

// ============================================
// SESSION CHECK
// ============================================

const checkSession = async () => {
  try {
    const response = await fetch('check_session.php');
    const data = await response.json();
    if (!data.logged_in) {
      window.location.href = 'login.php';
    }
  } catch (error) {
    console.error('Session check error:', error);
  }
};

// ============================================
// PASSWORD TOGGLE
// ============================================

const togglePassword = (inputId) => {
  const input = document.getElementById(inputId);
  if (input) {
    input.type = input.type === 'password' ? 'text' : 'password';
  }
};

// ============================================
// LOCAL STORAGE - REMEMBER ME
// ============================================

const rememberUser = (email) => {
  localStorage.setItem('lastEmail', email);
};

const loadRemembered = () => {
  const email = localStorage.getItem('lastEmail');
  if (email) {
    const emailInput = document.getElementById('email');
    if (emailInput) {
      emailInput.value = email;
    }
  }
};

// ============================================
// UTILITY FUNCTIONS
// ============================================

// Show notification/alert
const showNotification = (message, type = 'info') => {
  const notif = document.createElement('div');
  notif.className = `notification notification-${type}`;
  notif.textContent = message;
  document.body.appendChild(notif);
  
  setTimeout(() => notif.remove(), 3000);
};

// Redirect after delay
const redirectAfter = (url, delay = 2000) => {
  setTimeout(() => {
    window.location.href = url;
  }, delay);
};

// Clear form fields
const clearForm = (formId) => {
  const form = document.getElementById(formId);
  if (form) form.reset();
};
