alter table boards modify column image varchar(255) not null default '';
alter table boards modify column includeheader text not null default '';
alter table boards modify column locale varchar(30) CHARACTER SET latin1 not null default 'en';
alter table boards modify column enablefaptcha int(1) not null default 0;
alter table boards modify column enableporn int(1)  not null default 0;
alter table boards modify column `loadbalanceurl` varchar(255)  NOT NULL DEFAULT '';
alter table boards modify column `loadbalancepassword` varchar(255)  NOT NULL DEFAULT '';
alter table boards add column `maxvideolength` int(5) NOT NULL DEFAULT '0';
alter table boards add column `enablesoundinvideo` tinyint(1) NOT NULL DEFAULT '0';

alter table filetypes add column mediatype varchar(10) not null default 'image';

update filetypes set mediatype = 'image' where filetype in ('jpg', 'png', 'gif');

