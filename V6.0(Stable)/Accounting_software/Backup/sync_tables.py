import mysql.connector

# Database connection parameters
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'accounting',
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
                price FLOAT NOT NULL,
                quantity INT NOT NULL,
                bill_amount FLOAT NOT NULL,
                net_total_collectable_from_customer FLOAT NOT NULL DEFAULT 0,
                net_total_collectable FLOAT NOT NULL,
                amount_received FLOAT NOT NULL,
                net_amount_received_from_customer FLOAT NOT NULL DEFAULT 0,
                net_amount_received FLOAT NOT NULL DEFAULT 0,
                total_collection_due_from_customer FLOAT NOT NULL DEFAULT 0,
                total_collection_due FLOAT NOT NULL,
                our_cost FLOAT NOT NULL,
                channel VARCHAR(255) NOT NULL,
                profit FLOAT NOT NULL,
                net_total_profit_from_customer FLOAT NOT NULL DEFAULT 0,
                margin DECIMAL(10, 4) NOT NULL,
                net_margin_from_customer DECIMAL(10,4) NOT NULL DEFAULT 0,
                net_total_profit FLOAT NOT NULL,
                net_margin DECIMAL(10, 4) NOT NULL,
                entry_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """
        cursor.execute(create_table_query)
        conn.commit()
        print("combined_table created successfully.")

        # Query to fetch all table names except 'combined_table'
        cursor.execute("SELECT table_name FROM information_schema.tables WHERE table_schema = 'accounting' AND table_name != 'combined_table'")
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
                    SUM(bill_amount) AS sum_bill_amount,
                    SUM(amount_received) AS sum_amount_received,
                    SUM(profit) AS sum_profit
                FROM combined_table
            ) sub 
            SET 
                ct.net_total_collectable = sub.sum_bill_amount,
                ct.net_amount_received = sub.sum_amount_received,
                ct.total_collection_due = sub.sum_bill_amount - sub.sum_amount_received,
                ct.net_total_profit = sub.sum_profit,
                ct.net_margin = (sub.sum_profit / sub.sum_bill_amount) * 100
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
                    SUM(bill_amount) AS sum_bill_amount,
                    SUM(amount_received) AS sum_amount_received,
                    SUM(profit) AS sum_profit
                FROM combined_table
                GROUP BY customer_name
            ) sub ON ct.customer_name = sub.customer_name
            SET 
                ct.net_total_collectable_from_customer = sub.sum_bill_amount,
                ct.net_amount_received_from_customer = sub.sum_amount_received,
                ct.total_collection_due_from_customer = sub.sum_bill_amount - sub.sum_amount_received,
                ct.net_total_profit_from_customer = sub.sum_profit,
                ct.net_margin_from_customer = (sub.sum_profit / sub.sum_bill_amount) * 100
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