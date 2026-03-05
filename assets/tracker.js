// Reload detection using beforeunload event listener
window.addEventListener('beforeunload', function (e) {
    e.preventDefault(); // Most browsers show a generic message
    e.returnValue = ''; // Chrome requires this
});

