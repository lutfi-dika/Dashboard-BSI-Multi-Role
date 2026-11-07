-- 011_otp_satu_penerima.sql
-- OTP untuk semua role dikirim ke satu email & nomor HP yang sama.
-- Index unik email dilepas karena email penerima boleh duplikat antar akun.
--
-- GANTI <EMAIL> dan <NOHP> sesuai penerima OTP Anda sebelum dijalankan.

ALTER TABLE users DROP INDEX uniq_users_email;

UPDATE users
SET email = '<EMAIL>',
    no_hp = '<NOHP>'
WHERE email IS NULL OR no_hp IS NULL OR email = '' OR no_hp = '';