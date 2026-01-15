function confirmAction(message) {
  return confirm(message || 'Are you sure?');
}

// Auto-dismiss flash messages after 5 seconds
(function() {
  'use strict';

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlashMessage);
  } else {
    initFlashMessage();
  }

  function initFlashMessage() {
    const flashMessage = document.getElementById('flash-message');

    if (flashMessage) {
      console.log('Flash message found, will auto-dismiss in 5 seconds');

      // Add fade-out after 5 seconds
      setTimeout(function() {
        console.log('Dismissing flash message');
        flashMessage.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        flashMessage.style.opacity = '0';
        flashMessage.style.transform = 'translateY(-20px)';

        // Remove from DOM after animation completes
        setTimeout(function() {
          flashMessage.remove();
          console.log('Flash message removed');
        }, 500);
      }, 5000);
    }
  }
})();
