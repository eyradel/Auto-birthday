import streamlit as st
import pandas as pd
import plotly.express as px
from datetime import datetime, timedelta
import requests
import json
import os
from dotenv import load_dotenv
from apscheduler.schedulers.background import BackgroundScheduler
import time

# Load environment variables
load_dotenv()

# Configure Streamlit page
st.set_page_config(
    page_title="4th-IR Birthday SMS System",
    layout="wide"
)

# Initialize session state
if 'birthday_data' not in st.session_state:
    st.session_state.birthday_data = None
if 'last_refresh' not in st.session_state:
    st.session_state.last_refresh = None

# Mnotify API Configuration
MNOTIFY_API_KEY = st.secrets['MNOTIFY_API_KEY']
SENDER_ID = st.secrets['SENDER_ID']
MNOTIFY_API_URL = "https://apps.mnotify.net/smsapi"

class BirthdayManager:
    def __init__(self):
        self.csv_file = "Birthdays_up.csv"
        self.log_file = f"birthday_sms_log_{datetime.now().strftime('%Y-%m-%d')}.txt"
        
    def load_data(self):
        """Load and process birthday data from CSV"""
        try:
            if not os.path.exists(self.csv_file):
                st.error(f"Data file {self.csv_file} not found!")
                return None
            
            # Read the CSV file
            df = pd.read_csv(self.csv_file)
            
            # Clean up column names and combine first and last names
            df['name'] = df['First Name'] + ' ' + df['Last Name']
            df['email'] = df['Email Address']
            df['phone'] = df['Mobile Number'].astype(str)
            
            # Convert birth date to datetime
            df['birth_date'] = pd.to_datetime(df['Birth Date'], format='%d/%m/%Y')
            
            # Use the provided Month and Day columns
            df['month'] = df['Month'].astype(int)
            df['day'] = df['Day'].astype(int)
            
            # Select and reorder columns
            df = df[['name', 'email', 'phone', 'birth_date', 'month', 'day']]
            
            return df
        except Exception as e:
            st.error(f"Error loading data: {str(e)}")
            return None

    def send_birthday_sms(self, phone, name):
        """Send birthday SMS using Mnotify API"""
        try:
            message = f"Happy Birthday {name}! 🎂 Wishing you a fantastic day filled with joy and celebration. From all of us at 4th-IR."
            
            # Format phone number to add country code if needed
            phone = str(phone).replace(" ", "").replace("-", "")
            if not phone.startswith("233"):
                phone = "233" + phone.lstrip("0")
            
            params = {
                'key': MNOTIFY_API_KEY,
                'to': phone,
                'msg': message,
                'sender_id': SENDER_ID
            }
            
            response = requests.get(MNOTIFY_API_URL, params=params)
            return response.json()
        except Exception as e:
            self.log_message(f"Error sending SMS to {name}: {str(e)}")
            return None

    def log_message(self, message):
        """Log messages to file"""
        with open(self.log_file, 'a') as f:
            timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            f.write(f"[{timestamp}] {message}\n")

    def get_todays_birthdays(self, df):
        """Get today's birthdays"""
        today = datetime.now()
        return df[
            (df['month'] == today.month) & 
            (df['day'] == today.day)
        ]

    def get_upcoming_birthdays(self, df, days=30):
        """Get upcoming birthdays within specified days"""
        today = datetime.now()
        upcoming = []
        
        for _, row in df.iterrows():
            next_birthday = datetime(today.year, row['month'], row['day'])
            if next_birthday < today:
                next_birthday = datetime(today.year + 1, row['month'], row['day'])
            
            days_until = (next_birthday - today).days
            if 0 < days_until <= days:
                upcoming.append({
                    **row.to_dict(),
                    'days_until': days_until
                })
        
        return pd.DataFrame(upcoming).sort_values('days_until')

def auto_send_birthday_messages():
    """Automated birthday message sending function"""
    manager = BirthdayManager()
    df = manager.load_data()
    if df is not None:
        todays_birthdays = manager.get_todays_birthdays(df)
        
        for _, person in todays_birthdays.iterrows():
            response = manager.send_birthday_sms(person['phone'], person['name'])
            if response and response.get('code') == '1000':
                manager.log_message(f"Successfully sent birthday SMS to {person['name']}")
            else:
                manager.log_message(f"Failed to send birthday SMS to {person['name']}")

def navbar():
    st.markdown(
        """
        <head><meta charset="UTF-8">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdbootstrap/4.19.1/css/mdb.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <style>
            header {visibility: hidden;}
            .main {
                margin-top: 80px;
                padding-top: 10px;
            }
            #MainMenu {visibility: hidden;}
            footer {visibility: hidden;}
            .navbar {
                padding: 1rem;
                margin-bottom: 2rem;
                background-color: #4267B2;
                color: white;
                z-index: 10;
                position: fixed;
                width: 100%;
            }
    
            }

           
        </style>
       <nav class="navbar fixed-top navbar-expand-lg navbar-dark text-bold shadow-sm" >
            <a class="navbar-brand-logo">
                <img src="https://www.4th-ir.com/favicon.ico" style='width:50px'>
                Birthday SMS System
            </a>
        </nav>
        """,
        unsafe_allow_html=True
    )

def main():
    navbar()
    st.title("4th-IR Birthday SMS System")
    
    # Initialize BirthdayManager
    manager = BirthdayManager()
    
    # Sidebar
    st.sidebar.title("Navigation")
    page = st.sidebar.radio("Go to", ["Dashboard", "Today's Birthdays", "Upcoming Birthdays", "Directory", "Send SMS", "Logs"])
    
    # Load data
    if st.session_state.birthday_data is None or st.session_state.last_refresh is None or \
       (datetime.now() - st.session_state.last_refresh).seconds > 300:  # Refresh every 5 minutes
        st.session_state.birthday_data = manager.load_data()
        st.session_state.last_refresh = datetime.now()
    
    df = st.session_state.birthday_data
    
    if df is None:
        st.error("Error loading birthday data. Please check the data file.")
        return
    
    if page == "Dashboard":
        col1, col2, col3 = st.columns(3)
        
        todays_birthdays = manager.get_todays_birthdays(df)
        upcoming = manager.get_upcoming_birthdays(df)
        
        with col1:
            st.markdown('<div class="metric-card">', unsafe_allow_html=True)
            st.metric("Today's Birthdays", len(todays_birthdays))
            st.markdown('</div>', unsafe_allow_html=True)
        with col2:
            st.markdown('<div class="metric-card">', unsafe_allow_html=True)
            st.metric("Upcoming (30 days)", len(upcoming))
            st.markdown('</div>', unsafe_allow_html=True)
        with col3:
            st.markdown('<div class="metric-card">', unsafe_allow_html=True)
            st.metric("Total People", len(df))
            st.markdown('</div>', unsafe_allow_html=True)
        
        # Birthday distribution chart
        st.subheader("Birthday Distribution by Month")
        monthly_dist = df.groupby(df['birth_date'].dt.strftime('%B')).size().reset_index()
        monthly_dist.columns = ['Month', 'Count']
        
        # Sort months chronologically
        month_order = ['January', 'February', 'March', 'April', 'May', 'June', 
                      'July', 'August', 'September', 'October', 'November', 'December']
        monthly_dist['Month'] = pd.Categorical(monthly_dist['Month'], categories=month_order, ordered=True)
        monthly_dist = monthly_dist.sort_values('Month')
        
        fig = px.bar(monthly_dist, x='Month', y='Count', 
                    title='Birthday Distribution',
                    color='Count',
                    color_continuous_scale='Viridis')
        fig.update_layout(
            showlegend=False
        )
        st.plotly_chart(fig, use_container_width=True)
        
        # Today's birthdays
        if not todays_birthdays.empty:
            st.subheader(" Today's Birthdays")
            st.dataframe(
                todays_birthdays[['name', 'email', 'phone', 'birth_date']],
                hide_index=True,
                column_config={
                    'birth_date': st.column_config.DateColumn('Birth Date', format='MMM DD, YYYY')
                }
            )
    
    elif page == "Today's Birthdays":
        st.header("Today's Birthdays")
        todays_birthdays = manager.get_todays_birthdays(df)
        
        if not todays_birthdays.empty:
            st.dataframe(
                todays_birthdays[['name', 'email', 'phone', 'birth_date']],
                hide_index=True,
                column_config={
                    'birth_date': st.column_config.DateColumn('Birth Date', format='MMM DD, YYYY')
                }
            )
            
            if st.button("Send Birthday Messages", type="primary"):
                for _, person in todays_birthdays.iterrows():
                    response = manager.send_birthday_sms(person['phone'], person['name'])
                    if response and response.get('code') == '1000':
                        st.success(f"Sent birthday message to {person['name']}")
                    else:
                        st.error(f"Failed to send message to {person['name']}")
        else:
            st.info("No birthdays today! 🎂")
    
    elif page == "Upcoming Birthdays":
        st.header("Upcoming Birthdays")
        upcoming = manager.get_upcoming_birthdays(df)
        
        if not upcoming.empty:
            st.dataframe(
                upcoming[['name', 'email', 'phone', 'birth_date', 'days_until']],
                hide_index=True,
                column_config={
                    'birth_date': st.column_config.DateColumn('Birth Date', format='MMM DD, YYYY'),
                    'days_until': st.column_config.NumberColumn('Days Until Birthday')
                }
            )
        else:
            st.info("No upcoming birthdays in the next 30 days!")
    
    elif page == "Directory":
        st.header("Directory")
        search = st.text_input("Search by name or email")
        
        filtered_df = df
        if search:
            filtered_df = df[
                df['name'].str.contains(search, case=False) |
                df['email'].str.contains(search, case=False)
            ]
        
        st.dataframe(
            filtered_df[['name', 'email', 'phone', 'birth_date']],
            hide_index=True,
            column_config={
                'birth_date': st.column_config.DateColumn('Birth Date', format='MMM DD, YYYY')
            }
        )
    
    elif page == "Send SMS":
        st.header("Send Manual SMS")
        
        message_template = st.text_area(
            "Message Template",
            "Happy Birthday {name}! 🎂 Wishing you a fantastic day filled with joy and celebration. From all of us at 4th-IR."
        )
        
        selected_people = st.multiselect(
            "Select Recipients",
            options=df['name'].tolist(),
            format_func=lambda x: f"{x} ({df[df['name']==x]['birth_date'].iloc[0].strftime('%B %d')})"
        )
        
        if st.button("Send Messages", type="primary"):
            for name in selected_people:
                person = df[df['name'] == name].iloc[0]
                response = manager.send_birthday_sms(person['phone'], person['name'])
                if response and response.get('code') == '1000':
                    st.success(f"Sent message to {name}")
                else:
                    st.error(f"Failed to send message to {name}")
    
    elif page == "Logs":
        st.header("System Logs")
        if os.path.exists(manager.log_file):
            with open(manager.log_file, 'r') as f:
                logs = f.read()
            st.text_area("Logs", logs, height=400)
        else:
            st.info("No logs for today yet.")

if __name__ == "__main__":
    # Initialize scheduler for automated messages
    scheduler = BackgroundScheduler()
    scheduler.add_job(auto_send_birthday_messages, 'cron', hour=6, minute=0)
    scheduler.start()
    
    main() 