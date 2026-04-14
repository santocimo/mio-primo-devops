# API Configuration

Guida per configurare l'app mobile con il backend PHP.

## Endpoint Base

L'app si connette al backend PHP tramite la variabile `apiUrl` in `environment.ts`.

### Configurazione per ambiente

**Development** (`src/environments/environment.ts`):
```typescript
apiUrl: 'http://localhost:8081'
```

**Production** (`src/environments/environment.prod.ts`):
```typescript
apiUrl: 'https://your-domain.com'
```

## Endpoint richiesti

Il backend DEVE esporre i seguenti endpoint:

### 1. Authentication

**POST /api/auth/login**
```json
Request:
{
  "username": "admin",
  "password": "admin123"
}

Response:
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin",
    "gym_id": 1
  },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### 2. Gyms/Locations

**GET /api/gyms**
```json
Response:
[
  {
    "id": 1,
    "name": "Centro Fitness Downtown",
    "address": "Via Roma 123",
    "city": "Milano",
    "phone": "02-1234567",
    "email": "info@gym.it",
    "category": "gym",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

Header richiesto: `Authorization: Bearer {token}`

### 3. Services

**GET /api/services?gym_id=1**
```json
Response:
[
  {
    "id": 1,
    "name": "Personal Training",
    "description": "Sessione di allenamento personalizzato",
    "duration": 60,
    "price": 50.00,
    "gym_id": 1,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

Header richiesto: `Authorization: Bearer {token}`

### 4. Appointments

**GET /api/appointments**
```json
Response:
[
  {
    "id": 1,
    "user_id": 1,
    "service_id": 1,
    "gym_id": 1,
    "appointment_date": "2024-12-25",
    "appointment_time": "10:30",
    "status": "scheduled",
    "notes": "Note personali",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

Header richiesto: `Authorization: Bearer {token}`

**POST /api/appointments**
```json
Request:
{
  "service_id": 1,
  "gym_id": 1,
  "appointment_date": "2024-12-25",
  "appointment_time": "10:30"
}

Response:
{
  "success": true,
  "message": "Appointment created",
  "appointment_id": 1
}
```

**DELETE /api/appointments/{id}**
```json
Response:
{
  "success": true,
  "message": "Appointment deleted"
}
```

### 5. User Profile

**GET /api/user/profile**
```json
Response:
{
  "id": 1,
  "name": "User Name",
  "email": "user@example.com",
  "phone": "+39 123 456 7890",
  "role": "user",
  "gym_id": 1,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**PUT /api/user/profile**
```json
Request:
{
  "name": "New Name",
  "email": "new@example.com",
  "phone": "+39 123 456 7890"
}

Response:
{
  "success": true,
  "message": "Profile updated"
}
```

## Implementazione nel backend PHP

Aggiungi questi endpoint al tuo backend. Di seguito un esempio per il login:

```php
<?php
// api/auth/login.php

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Valida input
    if (!isset($data['username'], $data['password'])) {
        throw new Exception('Username e password obbligatori');
    }
    
    // Query database
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$data['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($data['password'], $user['password'])) {
        throw new Exception('Credenziali invalide');
    }
    
    // Genera token JWT (usa una libreria come firebase/php-jwt)
    $token = generateJWT($user['id']);
    
    // Response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'gym_id' => $user['gym_id'] ?? null
        ],
        'token' => $token
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

## Authentication Token

L'app usa JWT (JSON Web Tokens) per l'autenticazione. Tutti gli endpoint protetti richiedono:

```
Authorization: Bearer {token}
```

Il token deve essere:
1. Generato al login
2. Salvato localmente nell'app
3. Inviato in ogni richiesta (automaticamente tramite interceptor)
4. Invalidato al logout

## Error Handling

Risposte di errore standard (4xx, 5xx):
```json
{
  "success": false,
  "message": "Descrizione dell'errore"
}
```

Codici HTTP suggeriti:
- `200`: Success
- `400`: Bad request (errori di validazione)
- `401`: Unauthorized (token mancante o scaduto)
- `403`: Forbidden (permessi insufficienti)
- `404`: Not found
- `500`: Server error

## Deployment Checklist

- [ ] Aggiorna `environment.prod.ts` con URL corretto
- [ ] Configura CORS nel backend per le richieste dall'app
- [ ] Implementa tutti gli endpoint richiesti
- [ ] Testa autenticazione su dispositivo
- [ ] Configura SSL/TLS (HTTPS obbligatorio)
- [ ] Implementa rate limiting
- [ ] Aggiungi logging per debug
- [ ] Testa con rete lenta/fluttuante

