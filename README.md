# KitePad

A minimalist, privacy-focused blogging/pasting platform designed for quick content creation and secure sharing.

[use it here](https://blog-one-dun-49.vercel.app/)

## Features

- **Privacy First**:
  - **End-to-End Encryption (E2EE)**: Encrypt content in the browser before sending it to the server.
  - **UUID Links**: Random, unguessable URLs (`/v/abc123...`) to prevent scraping.
  - **Password Protection**: Secure individual posts with a password.
  - **Burn After Reading**: Automatically delete posts after the first view.
  - **Expiration**: Set posts to expire after 10m, 1h, 1d, or 1w.
- **Rich Content**:
  - **Markdown Support**: Render markdown with `marked.js`.
  - **Math Formulas**: LaTeX/Math rendering via `KaTeX`.
  - **Syntax Highlighting**: Code block highlighting with `highlight.js`.
  - **Auto-Embed**: Automatic YouTube video embedding.
- **Lightweight**: Fast loading with a curated design system.
- **No Accounts**: Instant publishing without registration.

## Getting Started

1. **Clone Repo**:
   ```bash
   git clone https://github.com/irhdab/blog.git
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
├── style.css              # Centralized styling
└── vercel.json            # Vercel serverless configuration
```


## Screenshot
<img width="936" height="580" alt="Screenshot 2026-02-26 at 18 41 49" src="https://github.com/user-attachments/assets/20e0b914-53df-479e-bb99-999338811c31" />

## Security Notice

KitePad prioritizes security:

- **XSS Protection**: All output is sanitized via **DOMPurify**.
- **Secure ID**: Uses random hex strings instead of sequential numbers for public links.
- **Encryption**: E2EE ensures the server owner can never read your encrypted content.

## Contributing

Pull requests and forks are welcome!

_Project currently under active development._

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=irhdab/kitepad&type=date&legend=top-left)](https://www.star-history.com/#irhdab/kitepad&type=date&legend=top-left)
