-- Separate database for the test suite, so `make test` never touches dev data.
-- Runs only on the first start of an empty volume.
CREATE DATABASE testing;
