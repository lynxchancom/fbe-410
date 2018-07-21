delimiter |
drop procedure if exists `sp_bumplimits`|
create procedure `sp_bumplimits`()
begin
  declare done int default false;
  declare boardname varchar(75);
  declare bumplimit int;
  declare boards_cursor cursor for select name, maxreplies from boards order by name;
  declare continue handler for not found set done = true;

  open boards_cursor;
  boards_loop: LOOP
    fetch boards_cursor into boardname, bumplimit;
    if done then leave boards_loop; end if;
    set @boardquery = concat('select p.id as `', boardname,'`, count(pp.parentid) as `limit', bumplimit,'` from `posts_', boardname,'` p left join `posts_', boardname,'` pp on (pp.parentid = p.id) where p.parentid=0 and p.IS_DELETED = 0 group by p.id having count(pp.parentid) >', bumplimit);
    prepare boardstatment from @boardquery;
    execute boardstatment;
    deallocate prepare boardstatment;
  end LOOP;

  close boards_cursor;

end|
