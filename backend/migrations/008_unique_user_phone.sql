ALTER TABLE users
  ADD COLUMN handphone_normalized VARCHAR(50)
  GENERATED ALWAYS AS (
    NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(handphone),' ',''),'-',''),'(',''),')',''),'.',''),'')
  ) STORED,
  ADD UNIQUE KEY uq_users_handphone_normalized (handphone_normalized);
