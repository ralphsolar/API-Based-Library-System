# API Based Library Management System


**Author**: Ralph Jacob S. Solar  
**BS Information Technology 4B**
## Description 📖
The **API Based Library Management System** is an api-based application that allows users to register, authenticate, and manage a collection of books in a library. Users can add new books to the system and view the list of books. The system uses JWT (JSON Web Token) for secure user authentication and token-based authorization for access to certain endpoints.

## Software and Technologies  💻
- **PHP**: Server-side scripting language.
- **Slim Framework**: A micro framework for building RESTful APIs.
- **JWT (JSON Web Token)**: For secure user authentication and authorization.
- **MySQL**: Database for storing user and book information.
- **JSON**: For data exchange in API requests and responses.

## Main Highlights 🌟
1. **Dependencies and Configuration**:
   - The project uses the Slim framework and Firebase JWT for authentication.
   - The `composer.json` file manages dependencies, and the `vendor/autoload.php` is included to load necessary classes.

2. **JWT Middleware**:
   - A middleware that ensures requests are authenticated using a valid JWT token.
   - The token is validated and the user's information is extracted for further request processing.
   - Invalid or reused tokens are rejected.

3. **Token Generation**:
   - The `generateToken()` function creates a JWT token with an expiration time of 1 hour.
   - The token includes the user ID and username in the payload for authorization.

## Endpoints 🛠️

### 1. User Registration
- **Method**: `POST`
- **URL**: `/user/register`
- **Description**: This endpoint allows a new user to register by providing a unique username and password. Upon successful registration, the user’s credentials are stored in the database, and they can later authenticate to access the system. If the username is already taken, the system will return a failure response with an appropriate message.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "username": "ralphsolar24",
    "password": "ralph_dmmmsu24"
  }

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "data": null
  }
- **Sample Response (JSON) if failed and user already exist**:
  ```json
  {
    "status": "fail",
    "data": {
        "message": "Username exists. Try Again"
  }
  }
---
### 2. User Authentication
- **Method**: `POST`
- **URL**: `/user/authenticate`
- **Description**: This endpoint allows users to authenticate by providing their username and password. If the credentials are valid, the system generates and returns a JWT token, which the user can use for subsequent requests. The JWT token is required to interact with protected endpoints (such as adding books or borrowing books), ensuring that only authenticated users can access these actions.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "username": "ralphsolar24",
    "password": "ralph_dmmmsu24"
  }

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "token": "your-jwt-token",
    "data": {
        "userid": 1,
        "username": "ralphsolar24"
  }
  }
---
### 3. Add Book
- **Method**: `POST`
- **URL**: `/book/add`
- **Description**: This endpoint allows an authenticated user to add a new book to the library collection. To ensure security, the request must include a valid JWT token in the Authorization header. The book details (e.g., title, genre, description, publisher) are provided in the request body, and upon success, the new book is saved in the database. A new JWT token is returned to ensure the session remains valid after this action.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "title": "The Great Gatsby",
    "genre": "Fiction",
    "description": "A novel about the American dream.",
    "pub_yr": 1925,
    "publisher": "Scribner",
    "language": "English",
    "num_page": 180
  }

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 4. List Book
- **Method**: `GET`
- **URL**: `/books`
- **Description**: This endpoint retrieves the list of all books in the library, provided that the request includes a valid JWT token in the Authorization header. The response includes details about each book in the collection, such as title, genre, description, and more. This endpoint is essential for displaying the complete library collection to users.
- **Sample Request (No Payload)**:
  Send the request with the Authorization: Token <your-jwt-token> header.

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": [
    {
      "id": 1,
      "title": "The Great Gatsby",
      "genre": "Fiction",
      "description": "A novel about the American dream.",
      "pub_yr": 1925,
      "publisher": "Scribner",
      "language": "English",
      "num_page": 180
    },
    {
      "id": 2,
      "title": "1984",
      "genre": "Dystopian",
      "description": "A novel about a totalitarian regime.",
      "pub_yr": 1949,
      "publisher": "Secker & Warburg",
      "language": "English",
      "num_page": 328
    }
  ]
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 5. Borrow a Book
- **Method**: `POST`
- **URL**: `/book/borrow`
- **Description**: This endpoint allows users to borrow a book from the library. A user must provide the book ID of the book they wish to borrow, and the system checks whether the book is available. If available, the book is marked as unavailable, and a borrow record with a due date is created. If the book is not available, the system will return an error message indicating that the book cannot be borrowed at this time.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "bookid": 101
  }

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": {
        "message": "Book borrowed successfully.",
        "due_date": "2024-11-22T00:00:00"
  }
  }
- **Sample Response (JSON) if failed **:
  ```json
  {
  "status": "fail",
  "data": {
    "message": "Book is currently not available."
  }
  }
- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }

---
### 6. View Borrowed Books
- **Method**: `GET`
- **URL**: `/user/borrowed`
- **Description**: This endpoint allows authenticated users to retrieve a list of books they have borrowed and have not yet returned. It provides information such as the borrow date, due date, and the book's details. This is useful for users to keep track of their borrowed books and ensure timely returns.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (No JSON Payload)**:
  Send the request with the Authorization: Token <your-jwt-token> header.

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
  "new_token": "new-jwt-token",
  "data": [
    {
      "borrow_id": 1,
      "bookid": 101,
      "title": "Harry Potter and the Sorcerer's Stone",
      "genre": "Fantasy",
      "description": "The first book in the Harry Potter series.",
      "pub_yr": 1997,
      "language": "English",
      "num_page": 223,
      "publisher": "Bloomsbury",
      "borrow_date": "2024-11-15T12:00:00",
      "due_date": "2024-11-20T12:00:00"
    }
  ]
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 7. Return Borrowed Book
- **Method**: `POST`
- **URL**: `/book/return`
- **Description**: Returns a borrowed book, updates the borrow record to include the return date, and marks the book as available. Generates a new JWT token after the action is completed.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "bookid": 101
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
  "new_token": "<new_jwt_token>",
  "data": {
    "message": "Book returned successfully."
  }
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 8. Add Author
- **Method**: `POST`
- **URL**: `/author/add`
- **Description**: This endpoint allows users to add a new author to the system, providing details such as the author's name, age, biography, and nationality. This is essential for managing authorship information within the system. Authors can later be linked to books in the collection.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "name": "J.K. Rowling",
    "age": 54,
    "biography": "Author of the Harry Potter series.",
    "nationality": "British",
    "numbooks_pub": 7
  }

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 9. List Author
- **Method**: `GET`
- **URL**: `/authors`
- **Description**: This endpoint retrieves a list of all authors in the system, providing their details such as name, age, biography, and nationality. This information is important for users and librarians to understand the authorship of the books in the library.
- **Sample Request (No Payload)**:
  Send the request with the Authorization: Token <your-jwt-token> header.

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
  "new_token": "new-jwt-token",
  "data": [
    {
      "authorid": 1,
      "name": "J.K. Rowling",
      "age": 54,
      "biography": "Author of the Harry Potter series.",
      "nationality": "British",
      "numbooks_pub": 7
    },
    {
      "authorid": 2,
      "name": "George R.R. Martin",
      "age": 76,
      "biography": "Author of the A Song of Ice and Fire series.",
      "nationality": "American",
      "numbooks_pub": 5
    }
  ]
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 10. View Book-Author Collection
- **Method**: `GET`
- **URL**: `/book/author/view`
- **Description**: Retrieves a list of books along with their corresponding authors.
- **Sample Request (No Payload)**:
  Send the request with the Authorization: Token <your-jwt-token> header.

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": [
    {
      "collectionid": 1,
      "bookid": 101,
      "title": "Harry Potter and the Sorcerer's Stone",
      "pub_yr": 1997,
      "publisher": "Bloomsbury",
      "description": "The first book in the Harry Potter series.",
      "genre": "Fantasy",
      "language": "English",
      "num_page": 223,
      "authorid": 1,
      "name": "J.K. Rowling",
      "age": 54,
      "biography": "Author of the Harry Potter series.",
      "nationality": "British",
      "numbooks_pub": 7,
      "is_available": true
    },
    {
      "collectionid": 2,
      "bookid": 102,
      "title": "A Game of Thrones",
      "pub_yr": 1996,
      "publisher": "Bantam Books",
      "description": "The first book in the A Song of Ice and Fire series.",
      "genre": "Fantasy",
      "language": "English",
      "num_page": 694,
      "authorid": 2,
      "name": "George R.R. Martin",
      "age": 76,
      "biography": "Author of the A Song of Ice and Fire series.",
      "nationality": "American",
      "numbooks_pub": 5,
      "is_available": true
    }
  ]
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
    "status": "fail",
    "data": "Invalid or reused token"
  }
---
### 11. Add Book-Author Relationship
- **Method**: `POST`
- **URL**: `/book/author/add`
- **Description**: Adds a relationship between a book and an author (i.e., assigns an author to a book).
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "bookid": 101,
    "authorid": 1
  }

- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 12. Edit Book
- **Method**: `PUT`
- **URL**: `/book/edit`
- **Description**: Edits the details of an existing book in the library database.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "bookid": 101,
    "title": "Harry Potter and the Chamber of Secrets",
    "genre": "Fantasy",
    "description": "The second book in the Harry Potter series.",
    "pub_yr": 1998,
    "publisher": "Bloomsbury",
    "language": "English",
    "num_page": 251
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }
- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 13. Edit Author
- **Method**: `PUT`
- **URL**: `/author/edit`
- **Description**: Edits the details of an existing author in the library database.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "authorid": 1,
    "name": "J.K. Rowling",
    "age": 54,
    "biography": "Author of the Harry Potter series.",
    "nationality": "British",
    "numbooks_pub": 7
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }
- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 14. Edit Book with Author
- **Method**: `PUT`
- **URL**: `/book/author/edit`
- **Description**: Edits the relationship between a book and its author.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "collectionid": 1,
    "bookid": 101,
    "authorid": 1
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }
- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }

---
### 15. Delete Book
- **Method**: `DELETE`
- **URL**: `/book/delete`
- **Description**: Deletes a book from the library database.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "bookid": 101
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }
- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }
---
### 16. Delete Author
- **Method**: `DELETE`
- **URL**: `/author/delete`
- **Description**: Deletes an author from the library database.
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "authorid": 1
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }
- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }

---
### 17. Delete Book-Author Relationship
- **Method**: `DELETE`
- **URL**: `/book/author/delete`
- **Description**: Deletes the relationship between a book and an author (removes an assigned author from a book).
- **Authentication**: Requires a valid JWT token.
- **Sample Request (JSON Payload)**:
  ```json
  {
    "collectionid": 1
  }
- **Sample Response (JSON) if successful**:
  ```json
  {
    "status": "success",
    "new_token": "new-jwt-token",
    "data": null
  }

- **Sample Response (JSON) if failed**:
  ```json
  {
  "status": "fail",
  "data": "Invalid or reused token"
  }


---
