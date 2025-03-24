# 📦 PhpJsonDB - JSON Database Management Library 🚀

## 🌐 Overview

PhpJsonDB is a modern PHP library for managing databases using JSON files. It provides a simple, file-system based approach to database management with type-safe operations. Perfect for small to medium projects! 🚀

## 🌟 Key Features

- 💡 Lightweight JSON-based database management
- 📁 File-system based table storage
- 🔒 Type-safe CRUD operations
- 🔍 Advanced querying capabilities
- 📤 Export and import functionality

## 🛠️ Installation

```bash
# Instala fácilmente con Composer 🎉
composer require phpjsondb
```

## 🧰 Methods Overview

| 🔧 Method | 📝 Description |
|--------|-------------|
| `getTables()` | Gets a list of all tables. |
| `getTablesInfo()` | Gets detailed information of all tables. |
| `tableRecords()` | Return the number of all records in the table. |
| `tableExists()` | Checks if a table (directory) exists. |
| `selectTable()` | Selects a table (directory) to work with. |
| `createTable()` | Creates a new table (directory). |
| `truncate()` | Truncates the table, removing all records but keeping the table and its control file. |
| `dropTable()` | Deletes a table (directory) and all its records. |
| `select()` | Selects specific fields to be returned in the query result. |
| `where()` | Applies search criteria with type-safe operators. |
| `findById()` | Finds a record by its ID. |
| `insertAuto()` | Inserts a new record using an auto-incremental ID. |
| `insert()` | Inserts a new record. |
| `update()` | Updates records selected. |
| `updateById()` | Updates an existing record. |
| `delete()` | Deletes records selected. |
| `deleteById()` | Deletes a record. |
| `groupBy()` | Groups records by a field and applies aggregation functions. |
| `limit()` | Applies pagination to the query. |
| `orderBy()` | Applies ordering to the result set. |
| `getRecords()` | Gets the records in the current result set. |
| `countRecords()` | Gets the count of records in the current result set. |
| `existsById()` | Checks if a record with the given ID exists. |
| `hasRecords()` | Checks if any records exist in the current result set. |
| `exportDatabase()` | Exports the entire database to a JSON file. |
| `importDatabase()` | Imports a database from a JSON file. |

## 💡 Quick Example

```php
$db = new PhpJsonDB();
$db->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

## 🤝 Contributing

Contributions are welcome! 🌈 Submit pull requests or open issues.

## 📄 License

MIT License 
