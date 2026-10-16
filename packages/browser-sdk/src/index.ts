export interface AnalyticsConfig {
    endpoint: string;
    projectKey: string;
  }
  
  export interface AnalyticsEvent {
    event_id: string;
    event_name: string;
    event_source: "browser";
    visitor_id: string;
    session_id: string;
    page_url?: string;
    occurred_at: string;
    properties?: Record<string, unknown>;
  }
  
  export class Analytics {
    private endpoint: string;
    private projectKey: string;
    private visitorId: string;
    private sessionId: string;
  
    constructor(config: AnalyticsConfig) {
      this.endpoint = config.endpoint.replace(/\/$/, "");
      this.projectKey = config.projectKey;
  
      this.visitorId = this.getOrCreateId("visitor_id");
      this.sessionId = this.getOrCreateId("session_id");
    }
  
    private getOrCreateId(key: string): string {
      const storageKey = `restaurant_analytics_${key}`;
  
      try {
        const existing = key === "session_id"
          ? sessionStorage.getItem(storageKey)
          : localStorage.getItem(storageKey);
  
        if (existing) return existing;
  
        const id = crypto.randomUUID();
  
        if (key === "session_id") {
          sessionStorage.setItem(storageKey, id);
        } else {
          localStorage.setItem(storageKey, id);
        }
  
        return id;
      } catch {
        // Storage can be unavailable in restricted browser contexts.
        return crypto.randomUUID();
      }
    }
  
    trackPageView(): void {
      this.track("page_view", {
        page_title: document.title,
      });
    }
  
    track(
      eventName: string,
      properties: Record<string, unknown> = {},
    ): void {
      const event: AnalyticsEvent = {
        event_id: crypto.randomUUID(),
        event_name: eventName,
        event_source: "browser",
        visitor_id: this.visitorId,
        session_id: this.sessionId,
        page_url: window.location.href,
        occurred_at: new Date().toISOString(),
        properties,
      };
  
      void this.send(event);
    }
  
    private async send(event: AnalyticsEvent): Promise<void> {
      try {
        await fetch(`${this.endpoint}/api/v1/events`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Project-Key": this.projectKey,
          },
          body: JSON.stringify(event),
          keepalive: true,
        });
      } catch {
        // Analytics failures should not interrupt the main application.
        // Retry and durable buffering will be added in a later step.
      }
    }
  }