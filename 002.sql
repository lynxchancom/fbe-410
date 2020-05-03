-- fix default values for `staff` table
-- apply for existing database. new one doesn't need this

alter table `staff` modify `access` int not null default 0;
alter table `staff` modify `suspended` int not null default 0;
