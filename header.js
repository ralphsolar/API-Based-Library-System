// header.js

// Event listeners for buttons
document.getElementById('addBookBtn').addEventListener('click', function () {
    window.location.href = 'add-book.html';
});

document.getElementById('viewBookBtn').addEventListener('click', function () {
    window.location.href = 'view-book.html';
});

document.getElementById('addAuthorBtn').addEventListener('click', function () {
    window.location.href = 'add-author.html';
});

document.getElementById('viewAuthorBtn').addEventListener('click', function () {
    window.location.href = 'view-author.html';
});

document.getElementById('borrowBookBtn').addEventListener('click', function () {
    window.location.href = 'borrow-book.html';
});

document.getElementById('viewBorrowedBooksBtn').addEventListener('click', function () {
    window.location.href = 'view-borrowed.html';
});

document.getElementById('assignAuthorBtn').addEventListener('click', function () {
    window.location.href = 'assign-author.html';
});

document.getElementById('viewAssignedAuthorBookBtn').addEventListener('click', function () {
    window.location.href = 'view-authorbook.html';
});

document.getElementById('logoutBtn').addEventListener('click', function () {
    document.cookie = 'token=; Max-Age=0; path=/'; // Clear the token cookie
    alert('You have been logged out.');
    window.location.href = 'index.html'; // Redirect to login page after logout
});
