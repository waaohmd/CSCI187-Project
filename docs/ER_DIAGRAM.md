# ER Diagram — Campus Community Marketplace

This diagram shows the records in `data/store.json`. The app stores each entity as a top-level array and links records by ID.

```mermaid
erDiagram
    USER ||--o{ LISTING : "owns"
    USER ||--o{ HELP_POST : "owns"
    USER ||--o{ CONVERSATION : "owns post in"
    USER ||--o{ CONVERSATION : "responds in"
    USER ||--o{ MESSAGE : "sends"
    LISTING |o--o{ CONVERSATION : "receives response"
    HELP_POST |o--o{ CONVERSATION : "receives response"
    CONVERSATION ||--|{ MESSAGE : "contains"

    USER {
        string id PK "usr_ prefix"
        string name
        string email UK "verified when it ends in @scu.edu"
        string password_hash
        string residence
        string bio
        string created_at
    }

    LISTING {
        string id PK "lst_ prefix"
        string owner_id FK
        string name
        string description
        string_array photos "up to four"
        float price
        string category
        string condition
        string location "pickup location"
        string status "available or sold"
        string created_at
        string updated_at
    }

    HELP_POST {
        string id PK "hlp_ prefix"
        string owner_id FK
        string title
        string type
        string from_location "pickup location"
        string to_location "drop-off location"
        string date
        string time
        float offer
        string details
        string status "open"
        string created_at
        string updated_at
    }

    CONVERSATION {
        string id PK "cnv_ prefix"
        string post_type "listing or help"
        string post_id FK "listing or help post"
        string post_title "kept after the post is removed"
        string owner_id FK "post owner"
        string responder_id FK "buyer or mover"
        string pickup_location
        string dropoff_location
        string created_at
        string updated_at
    }

    MESSAGE {
        string id PK "msg_ prefix"
        string conversation_id FK
        string sender_id FK
        string body
        string created_at
    }
```

## Rules

- Each post and responder pair has at most one conversation.
- `post_type` decides whether `post_id` points to a listing or a help post.
- Removing a post keeps its conversations, so `post_id` can point to a record that no longer exists.
- `verified` is not stored. The app derives it from `email`.
