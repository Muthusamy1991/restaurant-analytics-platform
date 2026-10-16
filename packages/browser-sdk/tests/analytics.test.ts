import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { Analytics } from "../src/index.ts";

class MemoryStorage implements Storage {
  private values = new Map<string, string>();

  get length(): number {
    return this.values.size;
  }

  clear(): void {
    this.values.clear();
  }

  getItem(key: string): string | null {
    return this.values.get(key) ?? null;
  }

  key(index: number): string | null {
    return [...this.values.keys()][index] ?? null;
  }

  removeItem(key: string): void {
    this.values.delete(key);
  }

  setItem(key: string, value: string): void {
    this.values.set(key, value);
  }
}

describe("browser Analytics SDK", () => {
  beforeEach(() => {
    vi.stubGlobal("localStorage", new MemoryStorage());
    vi.stubGlobal("sessionStorage", new MemoryStorage());
    vi.stubGlobal("document", { title: "Spice & Slice" });
    vi.stubGlobal("window", {
      location: { href: "http://localhost:3000/" },
    });
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({ ok: true })
    );
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("sends a page view with visitor and session identifiers", async () => {
    const client = new Analytics({
      endpoint: "http://127.0.0.1:8000/",
      projectKey: "pk_test",
    });

    client.trackPageView();

    await vi.waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(1);
    });

    const [url, options] = vi.mocked(fetch).mock.calls[0];

    expect(url).toBe("http://127.0.0.1:8000/api/v1/events");
    expect(options?.method).toBe("POST");
    expect(options?.headers).toMatchObject({
      "Content-Type": "application/json",
      "X-Project-Key": "pk_test",
    });

    const body = JSON.parse(String(options?.body));

    expect(body.event_name).toBe("page_view");
    expect(body.event_source).toBe("browser");
    expect(body.page_url).toBe("http://localhost:3000/");
    expect(body.visitor_id).toEqual(expect.any(String));
    expect(body.session_id).toEqual(expect.any(String));
    expect(body.properties).toEqual({ page_title: "Spice & Slice" });
  });

  it("reuses stored visitor ids across instances", () => {
    const first = new Analytics({
      endpoint: "http://127.0.0.1:8000",
      projectKey: "pk_test",
    });
    const second = new Analytics({
      endpoint: "http://127.0.0.1:8000",
      projectKey: "pk_test",
    });

    first.track("menu_item_added", { item_id: "item-101" });
    second.track("menu_item_added", { item_id: "item-102" });

    const firstBody = JSON.parse(
      String(vi.mocked(fetch).mock.calls[0][1]?.body)
    );
    const secondBody = JSON.parse(
      String(vi.mocked(fetch).mock.calls[1][1]?.body)
    );

    expect(firstBody.visitor_id).toBe(secondBody.visitor_id);
    expect(firstBody.event_id).not.toBe(secondBody.event_id);
  });

  it("does not throw when delivery fails", () => {
    vi.mocked(fetch).mockRejectedValue(new Error("network down"));

    const client = new Analytics({
      endpoint: "http://127.0.0.1:8000",
      projectKey: "pk_test",
    });

    expect(() => client.track("checkout_completed")).not.toThrow();
  });
});
