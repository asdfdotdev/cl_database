# ChristopherL Database Class

The cl_database() class endeavors to make it easy to add database support to PHP scripts for a variety of needs by abstracting query construction.

* Supports MySQL/MariaDB, PostgreSQL, and SQL Server
* Uses prepared statements to prevent SQL injection
* Logs all queries for reference
* Automatically applies table prefix to statements
* Works in PHP 7.0+

## Examples

cl_database class supports standard database query actions with a simplified array syntax.

### Connecting to server:

```
$db = new \ChristopherL\Database([
    'server' => 'mysql',
    'host' => '127.0.0.1',
    'username' => 'dbuser,
    'password' => '123456,
    'database' => 'mydb',
    'port' => 3306,
    'prefix' => 'tbl_'
]);
```

### Querying connected server:

#### Selecting data:

```
$db->select(
    'my_table',
    [
        'my_table' => ['string_column'],
        'my_other_table' => ['string_column{my_alias}']
    ],
    [
        'AND' => [
            'my_table' => [
                'date_column[>=]' => date('Y-m-d'),
            ],
            'my_other_table' => [
                'date_column[>=]' => date('Y-m-d')
            ]
        ]
    ],
    [
        '[>]my_other_table' => ['fk_column', 'k_column']
    ]
);
```

Resulting query

```
SELECT  my_table.string_column, my_other_table.string_column AS my_alias 
FROM my_table 
	LEFT JOIN my_other_table ON (my_table.fk_column = my_other_table.k_column)  
WHERE my_table.date_column >= '2017-05-03'
	AND my_other_table.date_column >= '2017-05-03'
```

#### Inserting data:

```
$db->insert(
    'my_table',
    [
        [
            'number_column' => 500,
            'string_column' => 'some text',
            'boolean_column' => 1,
            'date_column' => date('Y-m-d'),
            'time_column' => date('H:i:s')
        ],
        [...]
    ]
);
```

Resulting query

```
INSERT INTO my_table(number_column, string_column, boolean_column, date_column, time_column) 
VALUES(500, 'some text', 1, '2017-05-03', '01:49:31')
```

> Multiple records can be inserted by including multiple column => value arrays.

#### Updating data:

```
$db->update(
    'my_table',
    [
        'string_column' => 'new value',
    ],
    [
        'AND' => [
            'my_table' => [
                'date_column[>]' => date('Y-m-d'),
                'boolean_column[=]' => 1
            ]
        ]
    ]
);
```

Resulting query

```
UPDATE my_table 
SET string_column = 'new value'
WHERE my_table.date_column >= '2017-05-03' AND my_table.boolean_column = 1;
```

#### Deleting data:

```
$db->delete(
    'my_table',
    [
        'AND' => [
            'my_table' => [
                'date_column[>]' => date('Y-m-d'),
                'boolean_column[=]' => 1
            ]
        ]
    ]
);
```

Resulting query

```
DELETE FROM my_table WHERE my_table.date_column >= '2017-05-03' AND my_table.boolean_column = 1;
```

### More Complex Queries

Advanced joins, where conditions, and aggregate methods are also supported.

```
$hcdb->select(
    'locations',
    [
        'locations' => ['PkID', 'Name', 'Address', 'Address2', 'City', 'State', 'Country','Zip', 'Lat', 'Lon', 'URL', 'Phone'],
        'events' => ['LocID[count]', 'StartDate[min]']
    ],
    [
        'AND' => [
            'locations' => [
                'Lon[!null]' => null,
                'Lat[!=]' => '',
                'Lon[!=]' => '',
                'IsActive[=]' => '1',
            ],
            'events' => [
                'LocID[>]' => '0',
                'IsActive[=]' => '1',
                'IsApproved[=]' => '1',
                'PkID[!null]' => null,
                'StartDate[>=]' => '2014-01-01',
            ]
        ],
        'GROUP' => [
            'locations' => ['PkID', 'Name', 'Address', 'Address2', 'City', 'State', 'Country', 'Zip', 'Lat', 'Lon', 'URL', 'Phone']
        ],
        'HAVING' => [
            'CountLocID[>]' => '0'
        ],
        'ORDER' => [
            'locations.Name'
        ]
    ],
    [
        '[>]events' => ['PkID', 'LocID']
    ]
);
```

Resulting query

```
SELECT hc_locations.PkID, hc_locations.Name, hc_locations.Address, hc_locations.Address2, hc_locations.City, hc_locations.State, hc_locations.Country, hc_locations.Zip, hc_locations.Lat, hc_locations.Lon, hc_locations.URL, hc_locations.Phone, COUNT(hc_events.LocID), MIN(hc_events.StartDate) 
FROM hc_locations 
	LEFT JOIN hc_events ON (hc_locations.PkID = hc_events.LocID) 
WHERE hc_locations.Lon IS NOT NULL
	AND  hc_locations.Lat != ''  
	AND  hc_locations.Lon != ''  
	AND  hc_locations.IsActive = 1  
	AND  hc_events.LocID > 0  
	AND  hc_events.IsActive = 1  
	AND  hc_events.IsApproved = 1  
	AND  hc_events.PkID IS NOT NULL  
	AND  hc_events.StartDate >= 2014-01-01  
GROUP BY hc_locations.PkID
HAVING COUNT(hc_events.LocID) > 0
ORDER BY hc_locations.Name
```

### Additional Examples
cl_database class will shortly be used in [Helios Calendar](https://github.com/chrislarrycarl/Helios-Calendar), which can be referenced for a variety of use case examples.


## Credits

Thanks to [Medoo](http://medoo.in/doc) for the syntax inspiration which I have mimicked (nearly identically).


## License
cl_session is made available under the [LGPL](http://www.gnu.org/licenses/lgpl-2.1.html).
