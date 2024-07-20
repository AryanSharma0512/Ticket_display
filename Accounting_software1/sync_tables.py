import mysql.connector

# Database connection parameters
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'acc_network',
}

def synchronize_tables():
    try:
        # Connect to the database
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Drop combined_table if it exists
        cursor.execute("DROP TABLE IF EXISTS combined_table")
        conn.commit()
        print("combined_table dropped successfully.")

        # Create combined_table with the new schema including customer_name
        create_table_query = """
            CREATE TABLE combined_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(255) NOT NULL,
                product VARCHAR(255) NOT NULL,
                amount_used FLOAT NOT NULL,
                channel VARCHAR(255) NOT NULL,
                net_total_used_from_customer FLOAT NOT NULL DEFAULT 0,
                net_total_used FLOAT NOT NULL DEFAULT 0,
                amount_paid FLOAT NOT NULL,
                net_amount_paid_to_customer FLOAT NOT NULL DEFAULT 0,
                net_amount_paid FLOAT NOT NULL DEFAULT 0,
                total_debt_due_to_customer FLOAT NOT NULL DEFAULT 0,
                total_debt_due FLOAT NOT NULL DEFAULT 0,
                entry_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """
        cursor.execute(create_table_query)
        conn.commit()
        print("combined_table created successfully.")

        # Query to fetch all table names except 'combined_table'
        cursor.execute("SELECT table_name FROM information_schema.tables WHERE table_schema = 'acc_network' AND table_name != 'combined_table'")
        tables = cursor.fetchall()

        # Synchronize each table into combined_table
        for table in tables:
            table_name = table[0]
            # Get column names excluding 'id'
            cursor.execute(f"SHOW COLUMNS FROM `{table_name}`")
            columns = [column[0] for column in cursor.fetchall() if column[0] != 'id']
            
            # Construct INSERT query excluding 'id' and adding customer_name
            columns_str = ', '.join(columns)
            sql_sync = f"INSERT INTO combined_table ({columns_str}, customer_name) SELECT {columns_str}, '{table_name}' FROM `{table_name}`"
            
            cursor.execute(sql_sync)
            print(f"Table '{table_name}' synchronized successfully.")

        # Calculate aggregates for combined_table
        aggregate_query = """
            UPDATE combined_table ct 
            JOIN (
                SELECT 
                    SUM(amount_used) AS sum_amount_used,
                    SUM(amount_paid) AS sum_amount_paid
                FROM combined_table
            ) sub 
            SET 
                ct.net_total_used = sub.sum_amount_used,
                ct.net_amount_paid = sub.sum_amount_paid,
                ct.total_debt_due = sub.sum_amount_used - sub.sum_amount_paid
        """
        cursor.execute(aggregate_query)
        conn.commit()
        print("Aggregates calculated and updated.")

        # Calculate aggregates for combined_table grouped by customer_name
        aggregate_query1 = """
            UPDATE combined_table ct 
            JOIN (
                SELECT 
                    customer_name,
                    SUM(amount_used) AS sum_amount_used,
                    SUM(amount_paid) AS sum_amount_paid
                FROM combined_table
                GROUP BY customer_name
            ) sub ON ct.customer_name = sub.customer_name
            SET 
                ct.net_total_used_from_customer = sub.sum_amount_used,
                ct.net_amount_paid_to_customer = sub.sum_amount_paid,
                ct.total_debt_due_to_customer = sub.sum_amount_used - sub.sum_amount_paid
        """
        cursor.execute(aggregate_query1)
        conn.commit()
        print("Aggregates calculated and updated as per customer name.")

    except mysql.connector.Error as err:
        print(f"Error: {err}")

    finally:
        # Close connection
        cursor.close()
        conn.close()

# Execute synchronization function
synchronize_tables()