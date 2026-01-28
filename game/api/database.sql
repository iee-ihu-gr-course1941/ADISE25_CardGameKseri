// Πίνακας των παικτών που θα παίζουν, αυτή τη στιγμή έχει 2 παίκτες άλλα θα μπορούσε να παραμετροποιηθεί παραπάνω ο κώδικας για να παίξουν παραπάνω παίκτες
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50) UNIQUE NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
 
// Ο πίνακας games περιέχει το κάθε παιχνίδι που είτε βρίσκεται σε εξέλιξη (active), είτε σε αναμονή παίκτη (waiting), είτε έχει ολοκληρωθεί (finished). 
// Μαζί με τον παίκτη που έχει σειρά, τις κάρτες που είναι στο τραπέζι και ο παίκτης που έκανε ξερή τελευταίος
CREATE TABLE games (
id INT AUTO_INCREMENT PRIMARY KEY,
status ENUM('waiting','active','finished') NOT NULL,
current_player INT NULL,
deck JSON NOT NULL,
table_cards JSON NOT NULL,
last_capture_player INT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (current_player) REFERENCES users(id),
FOREIGN KEY (last_capture_player) REFERENCES users(id));

// Ο πίνακας game_players περιέχει το παιχνίδι όταν θα δημιουργηθεί και θα κρατάει πληροφορίες για το χέρι του κάθε παίκτη καθώς και το σκορ του.
CREATE TABLE game_players (
id INT AUTO_INCREMENT PRIMARY KEY,
game_id INT NOT NULL,
user_id INT NOT NULL,
hand JSON NOT NULL,
score INT DEFAULT 0,
joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
UNIQUE (game_id, user_id),
FOREIGN KEY (game_id) REFERENCES games(id),
FOREIGN KEY (user_id) REFERENCES users(id));