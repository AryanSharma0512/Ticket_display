import mysql.connector

# Database connection
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="accounting"
)
cursor = conn.cursor()

# List of tables to combine
tables = ['kirthi', 'bhavesh', 'jb', 'shailesh', 'neelesh']

try:
    # Get the columns excluding 'id'
    cursor.execute(f"SHOW COLUMNS FROM {tables[0]}")
    columns = [row[0] for row in cursor.fetchall() if row[0] != 'id']
    columns_str = ", ".join(columns)

    # Construct UNION ALL query without 'id'
    union_query = ""
    for table in tables:
        if union_query:
            union_query += " UNION ALL "
        union_query += f"SELECT {columns_str} FROM {table}"

    # Create combined table without 'id'
    create_table_query = f"CREATE TABLE IF NOT EXISTS combined_table ({', '.join([f'{col} VARCHAR(255)' for col in columns])})"
    cursor.execute(create_table_query)

    # Insert combined data
    insert_query = f"INSERT INTO combined_table ({columns_str}) {union_query}"
    cursor.execute(insert_query)

    # Commit and close connection
    conn.commit()
    print(f"Data combined successfully into combined_table. {cursor.rowcount} rows inserted.")

except mysql.connector.Error as e:
    print(f"Error: {e}")

finally:
    if conn.is_connected():
        cursor.close()
        conn.close()