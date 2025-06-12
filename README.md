# 4th-IR Birthday SMS System

A modern, autonomous birthday SMS management system built with Streamlit and Python. The system automatically sends birthday wishes to people on their special day and provides a user-friendly interface for managing birthday information.

## Features

- 📊 Interactive dashboard with birthday statistics
- 🎂 Automatic birthday SMS sending at 6 AM
- 📅 View today's and upcoming birthdays
- 📱 Manual SMS sending capability
- 📋 Searchable directory of all contacts
- 📝 System logs for tracking message delivery
- 📈 Birthday distribution visualization

## Setup

1. Clone the repository:
```bash
git clone <repository-url>
cd Auto-birthday
```

2. Install dependencies:
```bash
pip install -r requirements.txt
```

3. Configure environment variables:
Create a `.env` file in the project root with:
```
MNOTIFY_API_KEY=your_api_key
SENDER_ID=your_sender_id
```

4. Run the application:
```bash
streamlit run app.py
```

## Data Format

The system expects a CSV file named `Birthdays_up.csv` with the following columns:
- name: Full name of the person
- email: Email address
- phone: Phone number (format: 233XXXXXXXXX)
- birth_date: Date of birth (YYYY-MM-DD)

If the file doesn't exist, the system will create a sample file with demo data.

## Automatic SMS Sending

The system automatically sends birthday messages at 6 AM to anyone whose birthday falls on the current day. You can also manually send messages through the interface.

## System Requirements

- Python 3.8+
- Internet connection for SMS API
- Mnotify API credentials

## License

MIT License
