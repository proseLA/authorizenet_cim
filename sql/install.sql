SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `customers_cc`;
CREATE TABLE `customers_cc`
(
    `index_id`            int(11)        NOT NULL AUTO_INCREMENT,
    `customers_id`        int(11)        NOT NULL,
    `payment_profile_id`  int(11)        NOT NULL,
    `last_four`           char(4)        NOT NULL,
    `exp_date`            char(7)        NOT NULL,
    `shipping_address_id` int(11)        NOT NULL DEFAULT 0,
    `enabled`             enum ('Y','N') NOT NULL DEFAULT 'N',
    `card_last_modified`  datetime       NOT NULL,
    PRIMARY KEY (`index_id`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

DROP TABLE IF EXISTS `cim_payments`;
CREATE TABLE `cim_payments`
(
    `payment_id`        int(11)                          NOT NULL AUTO_INCREMENT,
    `orders_id`         int(11)                          NOT NULL DEFAULT 0,
    `payment_number`    varchar(32)                      NOT NULL DEFAULT '',
    `payment_name`      varchar(40)                      NOT NULL DEFAULT '',
    `payment_amount`    decimal(14, 2)                   NOT NULL DEFAULT 0.00,
    `payment_type`      varchar(20)                      NOT NULL DEFAULT '',
    `date_posted`       datetime                         DEFAULT NULL,
    `last_modified`     datetime                         DEFAULT NULL,
    `purchase_order_id` int(11)                          NOT NULL DEFAULT 0,
    `refund_amount`     decimal(14, 2) unsigned zerofill NOT NULL DEFAULT 000000000000.00,
    PRIMARY KEY (`payment_id`),
    KEY `refund_index` (`orders_id`, `payment_number`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;


DROP TABLE IF EXISTS `cim_refunds`;
CREATE TABLE `cim_refunds`
(
    `refund_id`      int(11)        NOT NULL AUTO_INCREMENT,
    `payment_id`     int(11)        NOT NULL DEFAULT 0,
    `orders_id`      int(11)        NOT NULL DEFAULT 0,
    `refund_number`  varchar(32)    NOT NULL DEFAULT '',
    `refund_name`    varchar(40)    NOT NULL DEFAULT '',
    `refund_amount`  decimal(14, 2) NOT NULL DEFAULT 0.00,
    `refund_type`    varchar(20)    NOT NULL DEFAULT 'REF',
    `payment_number` varchar(32)    NOT NULL,
    `date_posted`    datetime       DEFAULT NULL,
    `last_modified`  datetime       DEFAULT NULL,
    PRIMARY KEY (`refund_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

DROP TABLE IF EXISTS `cim_payment_types`;
CREATE TABLE `cim_payment_types` (
  `payment_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL DEFAULT 1,
  `payment_type_code` varchar(4) NOT NULL DEFAULT '',
  `payment_type_full` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`payment_type_id`),
  UNIQUE KEY `type_code` (`payment_type_code`),
  KEY `type_code_2` (`payment_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `cim_payment_types` (`payment_type_id`, `language_id`, `payment_type_code`, `payment_type_full`) VALUES
(1,	1,	'CA',	'Cash'),
(2,	1,	'CK',	'Check'),
(3,	1,	'MO',	'Money Order'),
(4,	1,	'WU',	'Western Union'),
(5,	1,	'ADJ',	'Adjustment'),
(6,	1,	'REF',	'Refund'),
(7,	1,	'CC',	'Credit Card'),
(8,	1,	'MC',	'MasterCard'),
(9,	1,	'VISA',	'Visa'),
(10,	1,	'AMEX',	'American Express'),
(11,	1,	'DISC',	'Discover'),
(12,	1,	'DINE',	'Diners Club'),
(13,	1,	'SOLO',	'Solo'),
(14,	1,	'MAES',	'Maestro'),
(15,	1,	'JCB',	'JCB');




alter TABLE `customers`
    add `customers_customerProfileId`        int(11) NOT NULL DEFAULT 0 after `customers_paypal_ec`;


alter TABLE `orders`
    add `cc_authorized`       enum ('1','0','2') NOT NULL DEFAULT '0' after `ip_address`,
    add `cc_authorized_date`  datetime       DEFAULT NULL after `cc_authorized`,
    add `delivery_address_id` int(11)            NOT NULL DEFAULT 0 after `cc_authorized_date`,
    add `CIM_address_id`      int(11)            NOT NULL DEFAULT 0 after `delivery_address_id`,
    add `payment_profile_id`  int(11)            NOT NULL DEFAULT 0 after `CIM_address_id`,
    add `approval_code`       varchar(10)    DEFAULT NULL after `payment_profile_id`,
    add `transaction_id`      varchar(20)    DEFAULT NULL after `approval_code`,
    add `save_cc_data`        enum ('Y','N') DEFAULT NULL after `transaction_id`;

ALTER TABLE `address_book`
    add `CIM_address_id` int(11) NULL AFTER `entry_zone_id`;

ALTER TABLE `so_payments`
ADD `transaction_id` varchar(20) COLLATE 'utf8mb4_general_ci' NOT NULL,
ADD `payment_profile_id` int(11) unsigned zerofill NOT NULL AFTER `transaction_id`,
ADD `approval_code` varchar(10) COLLATE 'utf8mb4_general_ci' NULL AFTER `payment_profile_id`,
ADD `CIM_address_id` int(11) unsigned zerofill NOT NULL AFTER `approval_code`;



