// Login Form Submit
document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  
  if (email && password) {
    // Simulate login - redirect to dashboard
    alert('Login successful! Redirecting to dashboard...');
    window.location.href = 'dashboard.html';
  }
});