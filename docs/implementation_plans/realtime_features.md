# Real-time Features Implementation Plan

## 1. Feature Overview

This plan focuses on implementing real-time communication features for the Gravitycar Framework to support modern React applications that require live updates, notifications, and collaborative functionality. The system will provide WebSocket support and Server-Sent Events (SSE) for efficient real-time data synchronization.

## 2. Current State Assessment

**Current State**: No real-time capabilities exist in the framework
**Impact**: Modern React apps often need real-time updates for better UX
**Priority**: MEDIUM - Week 7-8 implementation

### 2.1 Missing Components
- WebSocket server implementation
- Server-Sent Events (SSE) support
- Real-time data update mechanisms
- Live notification system
- Collaborative editing infrastructure
- Real-time state synchronization

### 2.2 Use Cases to Support
- Live notifications (new messages, system alerts)
- Real-time data updates (dashboard metrics, live feeds)
- Collaborative editing (multiple users editing same content)
- Live activity feeds (user actions, system events)
- Progress tracking (long-running operations)
- Live chat and messaging

## 3. Requirements

### 3.1 Functional Requirements
- WebSocket server for bi-directional communication
- Server-Sent Events for one-way updates
- Real-time data broadcasting
- User-specific notification channels
- Room-based communication (channels/topics)
- Message queuing and delivery guarantees
- Connection management and reconnection
- Authentication integration
- Rate limiting and spam protection

### 3.2 Non-Functional Requirements
- High-performance WebSocket handling
- Scalable architecture for multiple concurrent connections
- Memory-efficient message broadcasting
- Reliable message delivery
- Low-latency communication
- Graceful connection failure handling

## 4. Design

### 4.1 Architecture Components

```php
// WebSocket Server Manager
class WebSocketServer {
    public function start(string $host, int $port): void;
    public function stop(): void;
    public function broadcastToAll(string $message): void;
    public function broadcastToChannel(string $channel, string $message): void;
    public function sendToUser(int $userId, string $message): void;
    public function sendToConnection(string $connectionId, string $message): void;
}

// Real-time Event Manager
class RealTimeEventManager {
    public function emit(string $event, array $data, array $recipients = []): void;
    public function subscribe(string $event, callable $callback): void;
    public function unsubscribe(string $event, callable $callback): void;
    public function getSubscribers(string $event): array;
}

// Connection Manager
class ConnectionManager {
    public function addConnection(Connection $connection): void;
    public function removeConnection(string $connectionId): void;
    public function getConnection(string $connectionId): ?Connection;
    public function getUserConnections(int $userId): array;
    public function getChannelConnections(string $channel): array;
}

// Server-Sent Events Controller
class SSEController {
    public function streamEvents(Request $request): Response;
    public function sendEvent(string $event, array $data, array $recipients = []): void;
    public function keepAlive(): void;
}

// Notification System
class NotificationSystem {
    public function sendNotification(Notification $notification): void;
    public function sendToUser(int $userId, string $message, string $type = 'info'): void;
    public function sendToChannel(string $channel, string $message, string $type = 'info'): void;
    public function markAsRead(int $notificationId, int $userId): void;
}
```

### 4.2 Database Schema

#### Real-time Connections Table
```sql
CREATE TABLE realtime_connections (
    id VARCHAR(64) PRIMARY KEY,
    user_id INT NULL,
    channel VARCHAR(100),
    connection_type ENUM('websocket', 'sse') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_channel (channel),
    INDEX idx_last_ping (last_ping),
    INDEX idx_is_active (is_active)
);
```

#### Notifications Table
```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id_read (user_id, is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);
```

#### Real-time Events Log
```sql
CREATE TABLE realtime_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(100) NOT NULL,
    channel VARCHAR(100),
    sender_id INT NULL,
    recipient_type ENUM('all', 'user', 'channel') NOT NULL,
    recipient_id VARCHAR(100) NULL,
    payload JSON NOT NULL,
    delivered_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_channel (channel),
    INDEX idx_recipient (recipient_type, recipient_id),
    INDEX idx_created_at (created_at)
);
```

## 5. Implementation Steps

### 5.1 Phase 1: Server-Sent Events (Week 1)

#### Step 1: SSE Controller Implementation
```php
class SSEController {
    private ConnectionManager $connectionManager;
    private RealTimeEventManager $eventManager;
    
    public function streamEvents(Request $request): Response {
        // Validate authentication
        $user = $this->getCurrentUser($request);
        if (!$user) {
            throw new UnauthorizedException("Authentication required for real-time events");
        }
        
        // Set up SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Register connection
        $connectionId = $this->connectionManager->addSSEConnection($user->id, $request);
        
        // Send initial connection confirmation
        $this->sendSSEEvent('connected', [
            'connection_id' => $connectionId,
            'user_id' => $user->id,
            'timestamp' => date('c')
        ]);
        
        // Keep connection alive and listen for events
        while (true) {
            // Check for pending events
            $events = $this->eventManager->getPendingEvents($user->id);
            foreach ($events as $event) {
                $this->sendSSEEvent($event['type'], $event['data']);
            }
            
            // Send keep-alive ping
            $this->sendSSEEvent('ping', ['timestamp' => date('c')]);
            
            // Flush output and sleep
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            sleep(1);
            
            // Check if connection is still alive
            if (connection_aborted()) {
                $this->connectionManager->removeConnection($connectionId);
                break;
            }
        }
    }
    
    private function sendSSEEvent(string $event, array $data): void {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
    }
}
```

#### Step 2: Event Broadcasting System
```php
class RealTimeEventManager {
    private array $subscribers = [];
    private DatabaseConnector $db;
    
    public function emit(string $event, array $data, array $recipients = []): void {
        $eventData = [
            'event_type' => $event,
            'payload' => $data,
            'created_at' => date('c'),
            'id' => uniqid()
        ];
        
        // Log event
        $this->logEvent($event, $data, $recipients);
        
        // Broadcast to subscribers
        if (empty($recipients)) {
            $this->broadcastToAll($eventData);
        } else {
            foreach ($recipients as $recipient) {
                $this->broadcastToRecipient($eventData, $recipient);
            }
        }
    }
    
    public function broadcastToChannel(string $channel, array $eventData): void {
        $connections = $this->connectionManager->getChannelConnections($channel);
        
        foreach ($connections as $connection) {
            $this->sendToConnection($connection->id, $eventData);
        }
    }
}
```

### 5.2 Phase 2: WebSocket Implementation (Week 1-2)

#### Step 1: WebSocket Server using ReactPHP
```php
use React\Socket\Server;
use React\Http\Server as HttpServer;
use Ratchet\Http\HttpServer as RatchetHttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketServer implements MessageComponentInterface {
    private SplObjectStorage $clients;
    private ConnectionManager $connectionManager;
    private RealTimeEventManager $eventManager;
    
    public function __construct() {
        $this->clients = new SplObjectStorage;
        $this->connectionManager = new ConnectionManager();
        $this->eventManager = new RealTimeEventManager();
    }
    
    public function onOpen(ConnectionInterface $conn): void {
        $this->clients->attach($conn);
        
        // Extract user from query parameters or headers
        $userId = $this->authenticateConnection($conn);
        
        if ($userId) {
            $connectionId = $this->connectionManager->addWebSocketConnection($userId, $conn);
            $conn->connectionId = $connectionId;
            $conn->userId = $userId;
            
            $this->sendToConnection($conn, [
                'type' => 'connected',
                'data' => [
                    'connection_id' => $connectionId,
                    'user_id' => $userId
                ]
            ]);
        } else {
            $conn->close();
        }
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }
        
        switch ($data['type']) {
            case 'join_channel':
                $this->handleJoinChannel($from, $data['channel']);
                break;
            case 'leave_channel':
                $this->handleLeaveChannel($from, $data['channel']);
                break;
            case 'send_message':
                $this->handleSendMessage($from, $data);
                break;
            case 'ping':
                $this->handlePing($from);
                break;
        }
    }
    
    public function onClose(ConnectionInterface $conn): void {
        $this->clients->detach($conn);
        
        if (isset($conn->connectionId)) {
            $this->connectionManager->removeConnection($conn->connectionId);
        }
    }
    
    public function broadcastToChannel(string $channel, array $message): void {
        $connections = $this->connectionManager->getChannelConnections($channel);
        
        foreach ($connections as $connectionData) {
            $conn = $this->findConnectionById($connectionData->id);
            if ($conn) {
                $this->sendToConnection($conn, $message);
            }
        }
    }
}
```

#### Step 2: WebSocket Server Launcher
```php
class WebSocketServerLauncher {
    public function start(string $host = '0.0.0.0', int $port = 8080): void {
        $loop = \React\EventLoop\Factory::create();
        $socket = new \React\Socket\Server("{$host}:{$port}", $loop);
        
        $wsServer = new WsServer(new WebSocketServer());
        $httpServer = new RatchetHttpServer($wsServer);
        
        $server = new \React\Http\Server($loop, $httpServer);
        $server->listen($socket);
        
        echo "WebSocket server started on {$host}:{$port}\n";
        
        $loop->run();
    }
}
```

### 5.3 Phase 3: Integration with ModelBase (Week 2)

#### Step 1: Real-time Model Events
```php
abstract class ModelBase {
    // ... existing code ...
    
    protected function afterSave(): void {
        parent::afterSave();
        
        // Emit real-time event for model changes
        $this->emitRealTimeEvent('model_updated', [
            'model' => static::class,
            'id' => $this->getId(),
            'data' => $this->toArray(),
            'action' => $this->isNewRecord() ? 'created' : 'updated'
        ]);
    }
    
    protected function afterDelete(): void {
        parent::afterDelete();
        
        // Emit real-time event for model deletion
        $this->emitRealTimeEvent('model_deleted', [
            'model' => static::class,
            'id' => $this->getId(),
            'action' => 'deleted'
        ]);
    }
    
    private function emitRealTimeEvent(string $event, array $data): void {
        $eventManager = ServiceLocator::get(RealTimeEventManager::class);
        $eventManager->emit($event, $data);
    }
}
```

#### Step 2: API Integration
```php
class ModelBaseAPIController {
    // ... existing code ...
    
    public function create(Request $request): array {
        $result = parent::create($request);
        
        // Broadcast creation event
        $this->broadcastModelEvent('created', $result['data']);
        
        return $result;
    }
    
    public function update(Request $request, int $id): array {
        $result = parent::update($request, $id);
        
        // Broadcast update event
        $this->broadcastModelEvent('updated', $result['data']);
        
        return $result;
    }
    
    private function broadcastModelEvent(string $action, array $modelData): void {
        $eventManager = ServiceLocator::get(RealTimeEventManager::class);
        $eventManager->emit('api_model_' . $action, [
            'model' => $this->getModelName(),
            'action' => $action,
            'data' => $modelData,
            'timestamp' => date('c')
        ]);
    }
}
```

## 6. Real-time API Specification

### 6.1 Server-Sent Events Endpoint
```
GET /realtime/events
Authorization: Bearer {token}

Response Headers:
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive

Response Body:
event: connected
data: {"connection_id":"abc123","user_id":1,"timestamp":"2025-08-14T10:30:00+00:00"}

event: notification
data: {"type":"info","title":"New Message","message":"You have a new message","data":{"from_user":"John"}}

event: model_updated
data: {"model":"User","id":123,"action":"updated","data":{...}}

event: ping
data: {"timestamp":"2025-08-14T10:30:01+00:00"}
```

### 6.2 WebSocket Connection
```javascript
// Connect to WebSocket
const ws = new WebSocket('ws://localhost:8080?token=' + authToken);

// Connection opened
ws.onopen = function(event) {
    console.log('Connected to WebSocket');
    
    // Join a channel
    ws.send(JSON.stringify({
        type: 'join_channel',
        channel: 'user_123'
    }));
};

// Receive messages
ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};

// Send message
ws.send(JSON.stringify({
    type: 'send_message',
    channel: 'user_123',
    message: 'Hello World!'
}));
```

### 6.3 Notification API
```
POST /notifications/send
{
  "user_id": 123,
  "title": "New Message",
  "message": "You have received a new message",
  "type": "info",
  "data": {"from_user": "John", "message_id": 456}
}

GET /notifications
Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "New Message",
      "message": "You have received a new message",
      "type": "info",
      "is_read": false,
      "created_at": "2025-08-14T10:30:00+00:00"
    }
  ]
}

PUT /notifications/{id}/read
```

## 7. React Integration Examples

### 7.1 Real-time Hook
```typescript
const useRealTime = () => {
  const [connected, setConnected] = useState(false);
  const [events, setEvents] = useState<RealTimeEvent[]>([]);
  
  useEffect(() => {
    const eventSource = new EventSource('/realtime/events', {
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`
      }
    });
    
    eventSource.onopen = () => {
      setConnected(true);
    };
    
    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      setEvents(prev => [...prev, data]);
    };
    
    eventSource.onerror = () => {
      setConnected(false);
    };
    
    return () => {
      eventSource.close();
    };
  }, []);
  
  return { connected, events };
};
```

### 7.2 WebSocket Hook
```typescript
const useWebSocket = (url: string) => {
  const [socket, setSocket] = useState<WebSocket | null>(null);
  const [connected, setConnected] = useState(false);
  const [messages, setMessages] = useState<any[]>([]);
  
  useEffect(() => {
    const ws = new WebSocket(url);
    
    ws.onopen = () => {
      setConnected(true);
      setSocket(ws);
    };
    
    ws.onmessage = (event) => {
      const data = JSON.parse(event.data);
      setMessages(prev => [...prev, data]);
    };
    
    ws.onclose = () => {
      setConnected(false);
      setSocket(null);
    };
    
    return () => {
      ws.close();
    };
  }, [url]);
  
  const sendMessage = useCallback((message: any) => {
    if (socket && connected) {
      socket.send(JSON.stringify(message));
    }
  }, [socket, connected]);
  
  return { connected, messages, sendMessage };
};
```

### 7.3 Live Data Component
```typescript
const LiveUserList = () => {
  const [users, setUsers] = useState<User[]>([]);
  const { events } = useRealTime();
  
  // Listen for real-time user updates
  useEffect(() => {
    const userEvents = events.filter(event => 
      event.type === 'model_updated' && event.data.model === 'User'
    );
    
    userEvents.forEach(event => {
      const { action, data } = event.data;
      
      setUsers(prev => {
        switch (action) {
          case 'created':
            return [...prev, data];
          case 'updated':
            return prev.map(user => 
              user.id === data.id ? { ...user, ...data } : user
            );
          case 'deleted':
            return prev.filter(user => user.id !== data.id);
          default:
            return prev;
        }
      });
    });
  }, [events]);
  
  return (
    <div>
      {users.map(user => (
        <UserCard key={user.id} user={user} />
      ))}
    </div>
  );
};
```

## 8. Performance Optimization

### 8.1 Connection Management
- Connection pooling and reuse
- Automatic connection cleanup
- Heartbeat/ping mechanisms
- Connection limit per user

### 8.2 Message Broadcasting
- Efficient message queuing
- Batch message delivery
- Message deduplication
- Priority-based message handling

### 8.3 Memory Management
- Connection metadata cleanup
- Event log rotation
- Memory-efficient data structures
- Garbage collection optimization

## 9. Security Considerations

### 9.1 Authentication
- Token-based authentication for WebSocket
- Session validation for SSE
- User permission checking
- Channel access control

### 9.2 Rate Limiting
- Message rate limiting per connection
- Connection rate limiting per IP
- Channel subscription limits
- Spam protection mechanisms

### 9.3 Data Validation
- Message payload validation
- Channel name validation
- Event type whitelisting
- Input sanitization

## 10. Testing Strategy

### 10.1 Unit Tests
- Event manager functionality
- Connection management
- Message broadcasting
- Authentication integration

### 10.2 Integration Tests
- WebSocket server functionality
- SSE endpoint behavior
- Real-time model events
- Notification delivery

### 10.3 Performance Tests
- Concurrent connection handling
- Message broadcasting performance
- Memory usage under load
- Connection stability

## 11. Success Criteria

- [ ] SSE endpoint provides reliable real-time updates
- [ ] WebSocket server handles concurrent connections
- [ ] Model changes broadcast automatically
- [ ] Notification system works across channels
- [ ] React integration is smooth and responsive
- [ ] Performance is acceptable under load
- [ ] Security controls prevent abuse
- [ ] Connection management is reliable

## 12. Dependencies

### 12.1 External Libraries
- ReactPHP for WebSocket server
- Ratchet for WebSocket handling
- Redis for message queuing (optional)

### 12.2 Framework Components
- Authentication system for connection security
- ModelBase for automatic event emission
- Exception handling for error management
- Database connector for event logging

## 13. Risks and Mitigations

### 13.1 Performance Risks
- **Risk**: Memory leaks with long-running connections
- **Mitigation**: Regular cleanup, connection limits, monitoring

### 13.2 Scalability Risks
- **Risk**: Single server bottleneck
- **Mitigation**: Horizontal scaling, load balancing, message queue integration

### 13.3 Reliability Risks
- **Risk**: Connection drops and message loss
- **Mitigation**: Reconnection logic, message persistence, delivery confirmation

## 14. Estimated Timeline

**Total Time: 2 weeks**

- **Week 1**: SSE implementation, basic WebSocket server, connection management
- **Week 2**: ModelBase integration, notification system, React examples, testing

This implementation will provide a solid foundation for real-time features while maintaining the Gravitycar Framework's architecture principles and security standards.
