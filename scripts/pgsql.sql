DROP TABLE IF EXISTS galette_plugin_oauth2_access_tokens CASCADE;
CREATE TABLE galette_plugin_oauth2_access_tokens (
	token_id character varying(255) DEFAULT '' NOT NULL,
	id_adh int REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
	client_id varchar(255) NOT NULL,
	scopes JSON,
	expiry timestamp NOT NULL,
	revoked boolean DEFAULT false NOT NULL,
	PRIMARY KEY (token_id)
);
