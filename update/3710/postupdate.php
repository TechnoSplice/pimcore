<?php

$db = Pimcore\Db::get();

$db->query("CREATE TABLE `classificationstore_stores` (
	`storeId` INT NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NULL,
	`description` LONGTEXT NULL,
	PRIMARY KEY (`storeId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
");

$db->query("INSERT INTO `pimcore`.`classificationstore_stores` (`storeId`, `name`, `description`) VALUES (1, 'Default', 'Default Store');");


$db->query("ALTER TABLE `classificationstore_keys`
	ADD COLUMN `storeId` INT NULL DEFAULT NULL AFTER `id`,
	ADD INDEX `storeId` (`storeId`);
");

$db->query("ALTER TABLE `classificationstore_groups`
	ADD COLUMN `storeId` INT NULL DEFAULT NULL AFTER `id`,
	ADD INDEX `storeId` (`storeId`);
");

$db->query("ALTER TABLE `classificationstore_collections`
	ADD COLUMN `storeId` INT NULL DEFAULT NULL AFTER `id`,
	ADD INDEX `storeId` (`storeId`);
");

$db->query("ALTER TABLE `classificationstore_relations`
	ADD COLUMN `storeId` INT NOT NULL FIRST,
	DROP PRIMARY KEY,
	ADD PRIMARY KEY (`groupId`, `keyId`, `storeId`),
	ADD INDEX `storeId` (`storeId`);
");

$db->query("ALTER TABLE `classificationstore_collectionrelations`
	ADD COLUMN `storeId` INT NOT NULL FIRST,
	DROP PRIMARY KEY,
	ADD PRIMARY KEY (`colId`, `groupId`, `storeId`),
	ADD INDEX `storeId` (`storeId`);
");

$db->query("UPDATE classificationstore_keys set storeId = 1");
$db->query("UPDATE classificationstore_groups set storeId = 1");
$db->query("UPDATE classificationstore_collections set storeId = 1");
$db->query("UPDATE classificationstore_relations set storeId = 1");
$db->query("UPDATE classificationstore_collectionrelations set storeId = 1");
