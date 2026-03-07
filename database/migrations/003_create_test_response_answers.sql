CREATE TABLE test_response_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id BIGINT UNSIGNED NOT NULL,
    question_number TINYINT UNSIGNED NOT NULL,
    selected_option CHAR(1) NOT NULL,
    is_correct TINYINT(1) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tra_response FOREIGN KEY (response_id) REFERENCES test_responses (id) ON DELETE CASCADE,
    UNIQUE KEY uq_response_question (response_id, question_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

