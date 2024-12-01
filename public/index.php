<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


require '../src/vendor/autoload.php';

session_start();

$config = ['settings' => ['displayErrorDetails' => true]];
$app = new \Slim\App($config);

$secretKey = 'secret_key_ni_ralph_solar';

// Middleware for JWT validation and token rotation
$jwtMiddleware = function (Request $request, Response $response, $next) use ($secretKey) {
    $authHeader = $request->getHeader('Authorization');
    if (!$authHeader) {
        return $response->withStatus(401)->write(json_encode(array("status"=>"fail", "data"=>"Unauthorized")));
    }

    $token = str_replace('Token ', '', $authHeader[0]);

    if (!isset($_SESSION['valid_tokens']) || !in_array($token, $_SESSION['valid_tokens'])) {
        return $response->withStatus(401)->write(json_encode(array("status"=>"fail", "data"=>"Invalid or reused token")));
    }

    try {
        // Decode the token
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

        // Ensure the 'userid' is set in the decoded token
        if (!isset($decoded->data->userid)) {
            return $response->withStatus(401)->write(json_encode(array("status"=>"fail", "data"=>"User ID not found in token")));
        }

        $request = $request->withAttribute('jwt', $decoded);

        // Invalidate the token (remove from valid tokens list)
        $_SESSION['valid_tokens'] = array_diff($_SESSION['valid_tokens'], [$token]);

        // Proceed with the request
        return $next($request, $response);

    } catch (Exception $e) {
        return $response->withStatus(401)->write(json_encode(array("status"=>"fail", "data"=>"Invalid token")));
    }
};


function generateToken($payload, $secretKey) {
    if (is_null($payload) || empty((array)$payload)) {
        throw new Exception('Invalid payload: Payload cannot be null or empty.');
    }

    if (is_object($payload)) {
        $payload = (array) $payload;
    }

    $issuedAt = time();
    $expirationTime = $issuedAt + 3600; // 1 hour expiration

    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'data' => $payload // Ensure userid is part of this payload
    ];

    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    if (!isset($_SESSION['valid_tokens'])) {
        $_SESSION['valid_tokens'] = [];
    }
    $_SESSION['valid_tokens'][] = $jwt;

    return $jwt;
}


// user registration
$app->post('/user/register', function (Request $request, Response $response, array $args) {
    $data = json_decode($request->getBody());
    $usr = $data->username;
    $pass = $data->password;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $checkUserSql = "SELECT COUNT(*) FROM users WHERE username = '".$usr."'";
        $stmt = $conn->query($checkUserSql);
        $userExists = $stmt->fetchColumn();

        if ($userExists > 0) {
            $response->getBody()->write(json_encode(array("status" => "fail", "data" => array(
                "message" => "Username exists. Try Again"))));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $hashedPassword = hash('SHA256', $pass);
        $sql = "INSERT INTO users (username, password) VALUES ('".$usr."', '".$hashedPassword."')";
        $conn->exec($sql);

        $response->getBody()->write(json_encode(array("status" => "success", "data" => null)));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array(
            "title" => $e->getMessage()))));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
});

// User authentication (with token generation)
$app->post('/user/authenticate', function (Request $request, Response $response, array $args) use ($secretKey) {
    $data = json_decode($request->getBody());
    $usr = $data->username;
    $pass = $data->password;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $usr);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $data = $stmt->fetch();

        $hashedInputPassword = hash('SHA256', $pass);

        if ($data && $hashedInputPassword === $data['password']) {
            $iat = time(); 
            $exp = $iat + 3600; 

            $payload = [
                'userid' => $data['userid'], // Include userid in payload
                'username' => $data['username']
            ];

            // Generate token and return in response
            $jwt = generateToken($payload, $secretKey);

            $response->getBody()->write(json_encode(array(
                "status" => "success",
                "token" => $jwt,  // Include the token in the authentication response
                "data" => array(
                    "userid" => $data['userid'],
                    "username" => $data['username']
                )
            )));
        } else {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Authentication failed!")
            )));
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
    }
    return $response;
});


// Book addition (uses token and rotates it)
$app->post('/book/add', function (Request $request, Response $response, array $args) use ($secretKey) {
    $data = json_decode($request->getBody());
    $title = $data->title;
    $genre = $data->genre;
    $description = $data->description;
    $pub_yr = $data->pub_yr;
    $publisher = $data->publisher;
    $language = $data->language;
    $num_page = $data->num_page;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "INSERT INTO books (title, genre, description, pub_yr, language, num_page, publisher)
                VALUES ('".$title."', '".$genre."', '".$description."', '".$pub_yr."', '".$language."', '".$num_page."', '".$publisher."')";

        $conn->exec($sql);

        // Generate new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response body
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "new_token" => $jwt,
            "data" => null
            
        )));

    } catch(PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status"=>"fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);

// List of books (uses token and rotates it)
$app->get('/books', function (Request $request, Response $response, array $args) use ($secretKey) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
        $sql = "SELECT * FROM books";
        $stmt = $conn->query($sql);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "new_token" => $jwt,
            "data" => $books
            
        )));
    
    } catch(PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);


// Add author (uses token and rotates it)
$app->post('/author/add', function (Request $request, Response $response, array $args) use ($secretKey) {
    $data = json_decode($request->getBody());
    $name = $data->name;
    $age = $data->age;
    $biography = $data->biography;
    $nationality = $data->nationality;
    $numbooks_pub = $data->numbooks_pub;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
        $sql = "INSERT INTO authors (name, age, biography, nationality, numbooks_pub) 
                VALUES ('".$name."', '".$age."', '".$biography."', '".$nationality."', '".$numbooks_pub."')";
        $conn->exec($sql);

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "new_token" => $jwt,
            "data" => null
            
        )));
    
    } catch(PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);


// List of authors (uses token and rotates it)
$app->get('/authors', function (Request $request, Response $response, array $args) use ($secretKey) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
        $sql = "SELECT * FROM authors";
        $stmt = $conn->query($sql);
        $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "new_token" => $jwt,
            "data" => $authors
            
        )));
    
    } catch(PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);


// List book-author collections (uses token and rotates it)
$app->get('/book/author/view', function (Request $request, Response $response, array $args) use ($secretKey) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
        $sql = "SELECT book_authors.collectionid, books.bookid, books.title, books.pub_yr, books.publisher,
                books.description, books.genre, books.language, books.num_page, authors.authorid, authors.name, 
                authors.age, authors.biography, authors.nationality, authors.numbooks_pub, books.is_available
                FROM book_authors 
                JOIN books ON book_authors.bookid = books.bookid
                JOIN authors ON book_authors.authorid = authors.authorid";
        $stmt = $conn->query($sql);
        $book_authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "new_token" => $jwt,
            "data" => $book_authors
           
        )));
    
    } catch(PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);

// GET /book/{bookid} - Get a single book by its ID with JWT authentication and token rotation
$app->get('/book/{bookid}', function (Request $request, Response $response, $args) use ($secretKey) {
    $bookid = $args['bookid']; // Get the book ID from the URL
    
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        // Establish database connection
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare the SQL query to fetch the book with the given bookid
        $sql = "SELECT * FROM books WHERE bookid = :bookid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the book data
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the book exists
        if ($book) {
            // Generate a new token for the logged-in user
            $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

            // Return the book details and the new token
            $response->getBody()->write(json_encode(array(
                "status" => "success",
                "new_token" => $jwt, // Return the new token
                "data" => $book // Return the book details
            ), JSON_PRETTY_PRINT));

            return $response;
        } else {
            // If the book is not found, return a 404 response
            return $response->withJson(array(
                "status" => "fail",
                "message" => "Book not found"
            ), 404); // HTTP 404 Not Found
        }
    } catch (PDOException $e) {
        // If there is a database error, return a 500 response with the error message
        return $response->withJson(array(
            "status" => "fail",
            "message" => "Database error: " . $e->getMessage()
        ), 500); // HTTP 500 Internal Server Error
    }
})->add($jwtMiddleware); // Apply JWT middleware for token validation


$app->post('/book/author/add', function (Request $request, Response $response, array $args) use ($secretKey) {
    $data = json_decode($request->getBody());
    $bookid = $data->bookid;
    $authorid = $data->authorid;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if book exists
        $sql = "SELECT * FROM books WHERE bookid = :bookid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $response->getBody()->write(json_encode(array("status"=>"fail", "data"=>array("title"=>"Book ID does not exist"))));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Check if author exists
        $sql = "SELECT * FROM authors WHERE authorid = :authorid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':authorid', $authorid, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $response->getBody()->write(json_encode(array("status"=>"fail", "data"=>array("title"=>"Author ID does not exist"))));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Insert into book_authors
        $sql = "INSERT INTO book_authors (bookid, authorid) VALUES (:bookid, :authorid)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->bindParam(':authorid', $authorid, PDO::PARAM_INT);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array("status"=>"success", "new_token"=>$jwt, "data"=>null)));

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status"=>"fail", "data"=>array("title"=>$e->getMessage()))));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);
 

// edit book
$app->put('/book/edit', function (Request $request, Response $response) use ($secretKey) {
    $data = json_decode($request->getBody());
    $bookid = $data->bookid;
    $title = $data->title;
    $genre = $data->genre;
    $description = $data->description;
    $pub_yr = $data->pub_yr;
    $publisher = $data->publisher;
    $language = $data->language;
    $num_page = $data->num_page;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "UPDATE books SET title = :title, genre = :genre, description = :description, pub_yr = :pub_yr, 
                publisher = :publisher, language = :language, num_page = :num_page WHERE bookid = :bookid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':genre', $genre);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':pub_yr', $pub_yr);
        $stmt->bindParam(':publisher', $publisher);
        $stmt->bindParam(':language', $language);
        $stmt->bindParam(':num_page', $num_page);
        $stmt->bindParam(':bookid', $bookid);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array("status" => "success","new_token" => $jwt,"data" => null)));
    
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);



// edit author
$app->put('/author/edit', function (Request $request, Response $response) use ($secretKey) {
    $data = json_decode($request->getBody());
    $authorid = $data->authorid;
    $name = $data->name;
    $age = $data->age;
    $biography = $data->biography;
    $nationality = $data->nationality;
    $numbooks_pub = $data->numbooks_pub;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "UPDATE authors SET name = :name, age = :age, biography = :biography, nationality = :nationality, 
                numbooks_pub = :numbooks_pub WHERE authorid = :authorid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':age', $age);
        $stmt->bindParam(':biography', $biography);
        $stmt->bindParam(':nationality', $nationality);
        $stmt->bindParam(':numbooks_pub', $numbooks_pub);
        $stmt->bindParam(':authorid', $authorid);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array("status" => "success", "new_token" => $jwt, "data" => null)));
    
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);



//edit book with author
$app->put('/book/author/edit', function (Request $request, Response $response) use ($secretKey) {
    $data = json_decode($request->getBody());
    $collectionid = $data->collectionid;
    $bookid = $data->bookid;
    $authorid = $data->authorid;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "UPDATE book_authors SET bookid = :bookid, authorid = :authorid WHERE collectionid = :collectionid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bookid', $bookid);
        $stmt->bindParam(':authorid', $authorid);
        $stmt->bindParam(':collectionid', $collectionid);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        $response->getBody()->write(json_encode(array("status" => "success","new_token" => $jwt, "data" => null)));
    
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    return $response;
})->add($jwtMiddleware);




// delete a book by bookid
$app->delete('/book/delete', function (Request $request, Response $response) use ($secretKey) {
    $data = json_decode($request->getBody());
    $bookid = $data->bookid;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if book exists
        $sql = "SELECT * FROM books WHERE bookid = :bookid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return respondWithJson($response, ["status" => "fail", "data" => ["title" => "Book ID does not exist"]], 400);
        }

        // Delete book
        $sql = "DELETE FROM books WHERE bookid = :bookid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        return respondWithJson($response, ["status" => "success", "new_token" => $jwt, "data" => null], 200);

    } catch (PDOException $e) {
        return respondWithJson($response, ["status" => "fail", "data" => ["title" => $e->getMessage()]], 500);
    }
})->add($jwtMiddleware);


// delete author
$app->delete('/author/delete', function (Request $request, Response $response) use ($secretKey) {
    $data = json_decode($request->getBody());
    $authorid = $data->authorid;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if author exists
        $sql = "SELECT * FROM authors WHERE authorid = :authorid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':authorid', $authorid, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return respondWithJson($response, ["status" => "fail", "data" => ["title" => "Author ID does not exist"]], 400);
        }

        // Delete author
        $sql = "DELETE FROM authors WHERE authorid = :authorid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':authorid', $authorid, PDO::PARAM_INT);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        return respondWithJson($response, ["status" => "success", "new_token" => $jwt, "data" => null], 200);

    } catch (PDOException $e) {
        return respondWithJson($response, ["status" => "fail", "data" => ["title" => $e->getMessage()]], 500);
    }
})->add($jwtMiddleware);


// delete collection
$app->delete('/book/author/delete', function (Request $request, Response $response, array $args) use ($secretKey) {
    $data = json_decode($request->getBody());
    $collectionid = $data->collectionid;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the book_author relationship exists
        $sql = "SELECT * FROM book_authors WHERE collectionid = :collectionid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':collectionid', $collectionid, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return respondWithJson($response, ["status" => "fail", "data" => ["title" => "Collection ID does not exist"]], 400);
        }

        // Delete book_author relationship
        $sql = "DELETE FROM book_authors WHERE collectionid = :collectionid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':collectionid', $collectionid, PDO::PARAM_INT);
        $stmt->execute();

        // Generate a new token
        $jwt = generateToken($request->getAttribute('jwt')->data, $secretKey);

        // Include the new token in the response
        return respondWithJson($response, ["status" => "success", "new_token" => $jwt, "data" => null], 200);

    } catch (PDOException $e) {
        return respondWithJson($response, ["status" => "fail", "data" => ["title" => $e->getMessage()]], 500);
    }
})->add($jwtMiddleware);

// Borrow a book
$app->post('/book/borrow', function (Request $request, Response $response, array $args) use ($secretKey) {
    // Decode JSON body to associative array
    $data = json_decode($request->getBody(), true);
    $bookid = isset($data['bookid']) ? intval($data['bookid']) : null;

    if (!$bookid) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "Invalid or missing 'bookid'."]
        ], 400);
    }

    // Retrieve user information from JWT
    $jwtData = $request->getAttribute('jwt')->data;

    // Ensure 'userid' exists in JWT data
    if (!isset($jwtData->userid)) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "User ID not found in token."]
        ], 400);
    }

    $userid = $jwtData->userid;

    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the book exists and is available
        $checkBookSql = "SELECT is_available FROM books WHERE bookid = :bookid";
        $stmt = $conn->prepare($checkBookSql);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return respondWithJson($response, [
                "status" => "fail",
                "data" => ["message" => "Book not found."]
            ], 404);
        }

        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($book['is_available'] == 0) {
            return respondWithJson($response, [
                "status" => "fail",
                "data" => ["message" => "Book is currently not available."]
            ], 400);
        }

        // Begin transaction
        $conn->beginTransaction();

        // Mark the book as not available
        $updateBookSql = "UPDATE books SET is_available = 0 WHERE bookid = :bookid";
        $updateStmt = $conn->prepare($updateBookSql);
        $updateStmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $updateStmt->execute();

        // Calculate due_date (borrow_date + 5 days)
        $dueDate = date('Y-m-d H:i:s', strtotime('+5 days'));

        // Insert into borrow_records
        $insertBorrowSql = "INSERT INTO borrow_records (userid, bookid, due_date) VALUES (:userid, :bookid, :due_date)";
        $insertStmt = $conn->prepare($insertBorrowSql);
        $insertStmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $insertStmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $insertStmt->bindParam(':due_date', $dueDate, PDO::PARAM_STR);
        $insertStmt->execute();

        // Commit transaction
        $conn->commit();

        // Generate new token
        $jwt = generateToken($jwtData, $secretKey);

        return respondWithJson($response, [
            "status" => "success",
            "new_token" => $jwt,
            "data" => ["message" => "Book borrowed successfully.", "due_date" => $dueDate]
        ]);

    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["title" => "An internal error occurred."]
        ], 500);
    }
})->add($jwtMiddleware);


$app->get('/books/available', function (Request $request, Response $response) use ($secretKey) {
    // Retrieve user information from JWT
    $jwtData = $request->getAttribute('jwt')->data;

    // Ensure 'userid' exists in JWT data
    if (!isset($jwtData->userid)) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "User ID not found in token."]
        ], 400);
    }

    $userid = $jwtData->userid;

    // Fetch available books from the database
    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $booksQuery = "SELECT bookid, title, genre FROM books WHERE is_available = 1";
        $stmt = $conn->query($booksQuery);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate a new token for the user
        $jwt = generateToken($jwtData, $secretKey);

        return respondWithJson($response, [
            "status" => "success",
            "new_token" => $jwt,
            "books" => $books
        ]);
    } catch (PDOException $e) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "An error occurred while fetching available books."]
        ], 500);
    }
})->add($jwtMiddleware);



// View borrowed books (uses token and rotates it)
$app->get('/user/borrowed', function (Request $request, Response $response, array $args) use ($secretKey) {
    // Retrieve user information from JWT
    $jwtData = $request->getAttribute('jwt')->data;

    // Ensure 'userid' exists in JWT data
    if (!isset($jwtData->userid)) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "User ID not found in token."]
        ], 400);
    }

    $userid = $jwtData->userid;

    // Database connection parameters
    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Retrieve all borrowed books that have not been returned
        $sql = "SELECT br.borrow_id, b.bookid, b.title, b.genre, b.description, b.pub_yr, b.language, b.num_page, b.publisher, br.borrow_date, br.due_date
                FROM borrow_records br
                JOIN books b ON br.bookid = b.bookid
                WHERE br.userid = :userid AND br.return_date IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->execute();

        $borrowedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate new token
        $jwt = generateToken($jwtData, $secretKey);

        return respondWithJson($response, [
            "status" => "success",
            "new_token" => $jwt,
            "data" => $borrowedBooks
        ]);

    } catch(PDOException $e) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["title" => "An internal error occurred."]
        ], 500);
    }
})->add($jwtMiddleware);

// Return a book (uses token and rotates it)
$app->post('/book/return', function (Request $request, Response $response, array $args) use ($secretKey) {
    // Decode JSON body to associative array
    $data = json_decode($request->getBody(), true);
    $bookid = isset($data['bookid']) ? intval($data['bookid']) : null;

    if (!$bookid) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "Invalid or missing 'bookid'."]
        ], 400);
    }

    // Retrieve user information from JWT
    $jwtData = $request->getAttribute('jwt')->data;

    // Ensure 'userid' exists in JWT data
    if (!isset($jwtData->userid)) {
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["message" => "User ID not found in token."]
        ], 400);
    }

    $userid = $jwtData->userid;

    // Database connection parameters
    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "library";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the user has borrowed this book and hasn't returned it yet
        $checkBorrowSql = "SELECT borrow_id FROM borrow_records WHERE userid = :userid AND bookid = :bookid AND return_date IS NULL";
        $stmt = $conn->prepare($checkBorrowSql);
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return respondWithJson($response, [
                "status" => "fail",
                "data" => ["message" => "No active borrowing record found for this book."]
            ], 400);
        }

        $borrowRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        $borrowId = $borrowRecord['borrow_id'];

        // Begin transaction
        $conn->beginTransaction();

        // Update borrow_records with return_date
        $updateBorrowSql = "UPDATE borrow_records SET return_date = NOW() WHERE borrow_id = :borrow_id";
        $updateStmt = $conn->prepare($updateBorrowSql);
        $updateStmt->bindParam(':borrow_id', $borrowId, PDO::PARAM_INT);
        $updateStmt->execute();

        // Mark the book as available
        $updateBookSql = "UPDATE books SET is_available = 1 WHERE bookid = :bookid";
        $updateStmt = $conn->prepare($updateBookSql);
        $updateStmt->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $updateStmt->execute();

        // Commit transaction
        $conn->commit();

        // Generate new token
        $jwt = generateToken($jwtData, $secretKey);

        return respondWithJson($response, [
            "status" => "success",
            "new_token" => $jwt,
            "data" => ["message" => "Book returned successfully."]
        ]);

    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return respondWithJson($response, [
            "status" => "fail",
            "data" => ["title" => "An internal error occurred."]
        ], 500);
    }
})->add($jwtMiddleware);

// Function to handle JSON response
function respondWithJson($response, $data, $status = 200) {
    $response = $response->withHeader('Content-Type', 'application/json')
                         ->withStatus($status);
    $response->getBody()->write(json_encode($data));
    return $response;
}




$app->run();
