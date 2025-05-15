import mysql.connector
import random
import string
import hashlib
import time

# Database connection parameters
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'accounting',
}

def generate_customer_id():
    """Generates a random 5-character alphanumeric ID."""
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=5))

def generate_transaction_id():
    """Generates a unique 11-character transaction ID using a hash."""
    timestamp = str(time.time())  # Current timestamp as string
    random_chars = ''.join(random.choices(string.ascii_uppercase + string.digits, k=5))
    full_string = timestamp + random_chars
    return hashlib.sha256(full_string.encode()).hexdigest().upper()[:11]

def migrate_data():
    try:
        # Connect to the database
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Query to fetch all table names for individual customer data, excluding 'combined_table' and 'main_table'
        cursor.execute("""
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'accounting' 
            AND table_name NOT IN ('combined_table', 'main_table')
            AND table_name LIKE '%'
        """)
        tables = cursor.fetchall()

        # Store customer IDs to ensure uniqueness per customer/table
        customer_ids = {}

        for (table_name,) in tables:
            customer_id = generate_customer_id()
            # Ensure the customer_id is unique
            while customer_id in customer_ids.values():
                customer_id = generate_customer_id()
            customer_ids[table_name] = customer_id

            # Extract the customer name from the table name if needed
            customer_name = table_name.replace('customer_', '')  # Assuming customer name is embedded in the table name

            # Get data from each customer-specific table
            select_query = f"""
                SELECT '{customer_id}', '{customer_name}', product, price, quantity, our_cost, channel, amount_received, price*quantity - our_cost, entry_date
                FROM `{table_name}`
            """
            cursor.execute(select_query)
            rows = cursor.fetchall()

            # Prepare INSERT query for Main_table
            insert_query = """
                INSERT INTO Main_table (customer_id, customer_name, product, price, quantity, our_cost, channel, amount_received, profit, entry_date, transaction_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            # Migrate each row
            for row in rows:
                transaction_id = generate_transaction_id()  # Generate a transaction ID for each row
                full_row = row + (transaction_id,)  # Append transaction ID to each row data
                cursor.execute(insert_query, full_row)
            conn.commit()
            print(f"Data from '{table_name}' migrated successfully with Customer ID: {customer_id}.")

    except mysql.connector.Error as err:
        print(f"Error: {err}")
    finally:
        # Ensure the connection is closed
        if conn.is_connected():
            cursor.close()
            conn.close()
            print("MySQL connection is closed")

# Execute the migration function
migrate_data()
