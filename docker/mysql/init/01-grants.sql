-- Grant the gravitycar user privileges on test databases created by integration tests.
-- SchemaGeneratorIntegrationTest creates temporary databases named gravitycar_schema_test_<uniqueid>.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, LOCK TABLES
    ON `gravitycar\_schema\_test\_%`.*
    TO 'gravitycar'@'%';

FLUSH PRIVILEGES;
