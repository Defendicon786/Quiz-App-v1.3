-- SQL to create random_quiz_questions table
CREATE TABLE IF NOT EXISTS random_quiz_questions (
  quizid int(11) NOT NULL,
  qtype varchar(20) NOT NULL,
  qid int(11) NOT NULL,
  serialnumber int(11) NOT NULL,
  PRIMARY KEY (quizid, qtype, qid),
  CONSTRAINT fk_random_quiz_quizid FOREIGN KEY (quizid) REFERENCES quizconfig(quizid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 