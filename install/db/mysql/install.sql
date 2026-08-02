CREATE TABLE IF NOT EXISTS b_bx_image_webp_queue (
    ID int NOT NULL AUTO_INCREMENT,
    ELEMENT_ID int NOT NULL,
    IBLOCK_ID int NOT NULL,
    TARGET_TYPE char(1) NOT NULL COMMENT 'F=element field, P=property',
    TARGET_CODE varchar(50) NOT NULL,
    PROPERTY_VALUE_ID int DEFAULT NULL,
    FILE_ID int NOT NULL,
    STATUS char(1) NOT NULL DEFAULT 'P' COMMENT 'P=pending,W=working,E=error',
    ATTEMPTS int NOT NULL DEFAULT 0,
    LAST_ERROR text DEFAULT NULL,
    DATE_INSERT datetime NOT NULL,
    DATE_UPDATE datetime DEFAULT NULL,
    PRIMARY KEY (ID),
    KEY ix_bx_webp_status_id (STATUS, ID),
    KEY ix_bx_webp_file_status (FILE_ID, STATUS),
    KEY ix_bx_webp_element (ELEMENT_ID)
);
