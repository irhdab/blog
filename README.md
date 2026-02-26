# KitePad

A minimalist, privacy-focused blogging/pasting platform designed for quick content creation and secure sharing.

[use it here](https://blog-one-dun-49.vercel.app/)

## Features

- **Privacy & Security**:
  - **End-to-End Encryption (E2EE)**: Encrypt content in the browser before sending it to the server.
  - **UUID Links**: Random, unguessable URLs (`/v/abc123...`) to prevent scraping.
  - **Password Protection**: Secure individual posts with a password.
  - **Burn After Reading**: Automatically delete posts after the first view.
  - **View Limits**: Set a specific maximum number of views for each post.
  - **Expiration**: Set posts to expire after 10m, 1h, 1d, 1w, or Never.
  - **XSS Protection**: All output is sanitized via **DOMPurify**.

- **Rich Content & UX**:
  - **Markdown Support**: Render markdown with `marked.js`.
  - **Math Formulas**: LaTeX/Math rendering via `KaTeX`.
  - **Syntax Highlighting**: Code block highlighting with `highlight.js`.
  - **Auto-Embed**: Automatic YouTube video embedding.
  - **QR Code Support**: Generate QR codes for easy mobile sharing.
  - **Reading Time**: Automatic estimation of reading time.
  - **Custom Titles**: Optional titles for better organization.

- **Developer Friendly**:
  - **Edit & Clone**: Easily modify or duplicate existing posts (requires original password if protected).
  - **Raw Access**: Direct access to raw text by adding `&raw=1` to the URL.
  - **No Accounts**: Instant publishing without registration.
  - **Lightweight**: Fast loading with a curated minimalist design system.

## Getting Started

1. **Clone Repo**:
   ```bash
   git clone https://github.com/irhdab/kitepad.git
   ```
2. **Setup Database**:
   - The project uses PostgreSQL. Ensure you have a database and set the following environment variables:
     - `PGHOST`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, `PGPORT`
3. **Deployment**:
   - Optimized for **Vercel PHP** runtime.
   - Simply connect your repository to Vercel and it should "just work".

## Project Structure

```text
blog/
├── api/
│   ├── db.php             # Core database connection & migrations
│   ├── save_content.php   # Content storage (validation & UUID generation)
│   ├── view_content.php   # Content retrieval logic
│   └── view.phtml         # UI Template for viewing posts
├── index.html             # Main editor interface
├── privacy.html           # Privacy policy
├── style.css              # Centralized styling
└── vercel.json            # Vercel serverless configuration
```

## Screenshot

<img width="936" height="580" alt="Screenshot 2026-02-26 at 18 41 49" src="https://github.com/user-attachments/assets/20e0b914-53df-479e-bb99-999338811c31" />

## Security Notice

KitePad prioritizes security:

- **Zero-Knowledge**: E2EE ensures the server owner can never read your encrypted content.
- **Secure ID**: Uses random UUIDs instead of sequential numbers for public links.

## Contributing

Pull requests and forks are welcome!

_Project currently under active development._

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=irhdab/kitepad&type=date&legend=top-left)](https://www.star-history.com/#irhdab/kitepad&type=date&legend=top-left)
