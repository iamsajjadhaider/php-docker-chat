# 💬 Two-Tier Docker Chat Application (PHP & MySQL)

This project demonstrates a fully containerized, two-tier web application using **Docker Compose**. It consists of a frontend (PHP/Apache) that handles user input and a persistent backend (MySQL) that stores all chat messages.

This setup is ideal for learning how to manage dependencies, persistence, and service networking within the Docker ecosystem.

---

## 🚀 Quick Start: Running the Application

Follow these steps to get the chat application running instantly on your local machine.

### Prerequisites

You must have **Docker** and **Docker Compose** installed on your system.

### 1. Configure Environment Variables

The application requires a file named `.env` to manage sensitive database credentials.

1.  Copy the provided template file:
    ```bash
    cp template.env .env
    ```
2.  Open the newly created **`.env`** file and replace the placeholder values (like `YOUR_ROOT_PASSWORD`) with **strong, unique passwords.**

### 2. Build and Run the Containers

This command will build the custom PHP image, start both the `web` and `db` services, and create the Docker volume for persistent data.

```bash
docker compose up --build -d
