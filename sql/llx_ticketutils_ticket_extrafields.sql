CREATE TABLE llx_ticketutils_ticket_extrafields ( 
    `rowid` INT(11) NOT NULL AUTO_INCREMENT , PRIMARY KEY (`rowid`) ,
    `fk_ticket` INT(11) NOT NULL , 
    `rating` DOUBLE(24,8) NULL , 
    `rating_date` TIMESTAMP NULL , 
    `rating_comment` TEXT NULL ,
    `tms` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
    `fk_user_creator` INT(11) NOT NULL , 
    `fk_user_edit` INT(11) NOT NULL 
    ) ENGINE = InnoDB;