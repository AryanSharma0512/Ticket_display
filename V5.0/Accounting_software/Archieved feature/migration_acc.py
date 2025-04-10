import mysql.connector
from mysql.connector import Error
import random
import string

def generate_customer_id():
    """Generates a unique alphanumeric ID."""
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=5))

def transfer_data():
    # Connection details for the source and destination databases
    source_db_config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'acc_network'
    }

    dest_db_config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'accounts_network'  # Updated to your destination database name
    }

    try:
        # Establish connections to the source and destination databases
        source_conn = mysql.connector.connect(**source_db_config)
        dest_conn = mysql.connector.connect(**dest_db_config)
        if source_conn.is_connected() and dest_conn.is_connected():
            print("Connected to both databases successfully")

            # Prepare to collect unique account holder names and IDs
            account_holder_ids = {}

            # Cursor to fetch table names from source database
            source_cursor = source_conn.cursor()
            source_cursor.execute("SELECT table_name FROM information_schema.tables WHERE table_schema = 'acc_network' AND table_name NOT IN ('combined_table', 'acc_network_main')")
            tables = source_cursor.fetchall()

            # Cursor for operations on the destination database
            dest_cursor = dest_conn.cursor()
            for (table_name,) in tables:
                if table_name not in account_holder_ids:
                    account_holder_ids[table_name] = generate_customer_id()

                # Fetch data from the current table
                source_cursor.execute(f"SELECT product, amount_used, amount_paid, channel, entry_date FROM `{table_name}`")
                rows = source_cursor.fetchall()

                # Insert data into the destination table
                for row in rows:
                    insert_query = """
                    INSERT INTO acc_network_main (customer_id, account_holder, product_category, amount_used, amount_paid, channel, entry_date)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    """
                    dest_cursor.execute(insert_query, (account_holder_ids[table_name], table_name, *row))

            # Commit changes in the destination database
            dest_conn.commit()
            print("Data transferred successfully.")

    except Error as e:
        print("Error while connecting to MySQL", e)
    finally:
        # Close all connections and cursors
        if source_conn.is_connected():
            source_cursor.close()
            source_conn.close()
            print("MySQL connection is closed for source DB")
        if dest_conn.is_connected():
            dest_cursor.close()
            dest_conn.close()
            print("MySQL connection is closed for destination DB")

# Run the transfer function
transfer_data()
