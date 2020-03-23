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

DROP TABLE IF EXISTS `so_payments`;
CREATE TABLE `so_payments`
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


DROP TABLE IF EXISTS `so_refunds`;
CREATE TABLE `so_refunds`
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

alter TABLE `customers`
    add `customers_customerProfileId`        int(11) NOT NULL DEFAULT 0 after `customers_paypal_ec`,
    add `customers_customerPaymentProfileId` int(11) NOT NULL DEFAULT 0 after `customers_customerProfileId`;


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


