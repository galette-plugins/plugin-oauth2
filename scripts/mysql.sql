DROP TABLE IF EXISTS galette_plugin_oauth2_access_tokens;
CREATE TABLE galette_plugin_oauth2_access_tokens (
	token_id varchar(255) NOT NULL,
	id_adh int(10) unsigned NOT NULL,
	client_id varchar(255) NOT NULL,
	scopes JSON,
	expiry datetime NOT NULL,
	revoked tinyint(1) NOT NULL default 0,
	PRIMARY KEY (token_id),
	FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
