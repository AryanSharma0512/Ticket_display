import mysql.connector
import pandas as pd
import matplotlib.pyplot as plt

# Database connection parameters
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'acc_network',
}

# Connect to the database and fetch data
def fetch_data(query):
    conn = mysql.connector.connect(**db_config)
    df = pd.read_sql(query, conn)
    conn.close()
    return df

# Fetch data for the visualizations
combined_table_query = "SELECT * FROM combined_table"
df_combined = fetch_data(combined_table_query)

# Bar Chart of Total Collectable Amounts by Customer
plt.figure(figsize=(12, 6))
df_collectable = df_combined.groupby('customer_name')['net_total_collectable_from_customer'].sum().reset_index()
plt.bar(df_collectable['customer_name'], df_collectable['net_total_collectable_from_customer'], color='skyblue')
plt.xlabel('Customer Name')
plt.ylabel('Total Collectable Amount')
plt.title('Total Collectable Amounts by Customer')
plt.xticks(rotation=45)
plt.tight_layout()
plt.show()

# Pie Chart of Total Profit by Channel
plt.figure(figsize=(8, 8))
df_profit_channel = df_combined.groupby('channel')['net_total_profit'].sum().reset_index()
plt.pie(df_profit_channel['net_total_profit'], labels=df_profit_channel['channel'], autopct='%1.1f%%', startangle=140)
plt.title('Total Profit by Channel')
plt.tight_layout()
plt.show()

# Line Chart of Net Amount Received Over Time
plt.figure(figsize=(12, 6))
df_combined['entry_date'] = pd.to_datetime(df_combined['entry_date'])
df_time_series = df_combined.groupby('entry_date')['net_amount_received'].sum().reset_index()
plt.plot(df_time_series['entry_date'], df_time_series['net_amount_received'], marker='o', linestyle='-')
plt.xlabel('Date')
plt.ylabel('Net Amount Received')
plt.title('Net Amount Received Over Time')
plt.tight_layout()
plt.show()
