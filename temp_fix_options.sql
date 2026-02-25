UPDATE test_question SET options = '{"A": "Option A", "B": "Option B", "C": "Option C", "D": "Option D"}'::json WHERE (options = '[]' OR options IS NULL OR options = '{}') AND question_type IN ('qcm', 'qcm_single', 'qcm_multiple');

