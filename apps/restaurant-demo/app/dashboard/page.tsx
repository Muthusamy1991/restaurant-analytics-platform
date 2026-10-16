
"use client";

import { useCallback, useEffect, useState } from "react";

type Overview = {
  total_events: number;
  page_views: number;
  unique_visitors: number;
  orders_created: number;
  from: string;
  to: string;
};

type Trend = {
  date: string;
  total_events: number;
  page_views: number;
};

type PageItem = {
  page_url: string;
  views: number;
};

type AnalyticsEvent = {
  id: number;
  event_id: string;
  event_name: string;
  event_source: "browser" | "server";
  visitor_id: string | null;
  page_url: string | null;
  properties: Record<string, unknown> | string | null;
  occurred_at: string;
};

type PageResponse<T> = {
  data: T[];
  total?: number;
};

function dateOffset(days: number) {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toLocaleDateString("en-CA");
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString("en-IN", {
    day: "2-digit",
    month: "short",
  });
}

export default function DashboardPage() {
  const [from, setFrom] = useState(() => dateOffset(-6));
  const [to, setTo] = useState(() => dateOffset(0));
  const [overview, setOverview] = useState<Overview | null>(null);
  const [trends, setTrends] = useState<Trend[]>([]);
  const [pages, setPages] = useState<PageItem[]>([]);
  const [events, setEvents] = useState<AnalyticsEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [refresh, setRefresh] = useState(0);

  const loadDashboard = useCallback(async () => {
    const endpoint = process.env.NEXT_PUBLIC_ANALYTICS_URL;
    const projectKey = process.env.NEXT_PUBLIC_ANALYTICS_KEY;

    if (!endpoint || !projectKey) {
      setError("Analytics environment variables are missing.");
      setLoading(false);
      return;
    }

    if (from > to) {
      setError("The start date must be before or equal to the end date.");
      setLoading(false);
      return;
    }

    setLoading(true);
    setError("");

    try {
      const base = endpoint.replace(/\/$/, "");
      const query = new URLSearchParams({ from, to });
      const headers = { "X-Project-Key": projectKey };

      const [overviewResponse, trendsResponse, pagesResponse, eventsResponse] =
        await Promise.all([
          fetch(`${base}/api/v1/analytics/overview?${query}`, {
            headers,
            cache: "no-store",
          }),
          fetch(`${base}/api/v1/analytics/trends?${query}`, {
            headers,
            cache: "no-store",
          }),
          fetch(`${base}/api/v1/analytics/pages?${query}`, {
            headers,
            cache: "no-store",
          }),
          fetch(`${base}/api/v1/analytics/recent-events?${query}`, {
            headers,
            cache: "no-store",
          }),
        ]);

      const responses = [
        overviewResponse,
        trendsResponse,
        pagesResponse,
        eventsResponse,
      ];

      if (responses.some((response) => !response.ok)) {
        throw new Error(
          `Analytics API request failed (HTTP ${
            responses.find((response) => !response.ok)?.status
          }).`
        );
      }

      const [overviewResult, trendsResult, pagesResult, eventsResult] =
        await Promise.all(responses.map((response) => response.json()));

      setOverview(overviewResult as Overview);
      setTrends((trendsResult as PageResponse<Trend>).data ?? []);
      setPages((pagesResult as PageResponse<PageItem>).data ?? []);
      setEvents((eventsResult as PageResponse<AnalyticsEvent>).data ?? []);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Unable to load analytics."
      );
    } finally {
      setLoading(false);
    }
  }, [from, to]);

  useEffect(() => {
    void loadDashboard();
  }, [loadDashboard, refresh]);

  const maxEvents = Math.max(
    1,
    ...trends.map((item) => item.total_events)
  );
  const maxPageViews = Math.max(1, ...pages.map((item) => item.views));

  const metrics = [
    { label: "Total Events", value: overview?.total_events ?? 0 },
    { label: "Page Views", value: overview?.page_views ?? 0 },
    { label: "Unique Visitors", value: overview?.unique_visitors ?? 0 },
    { label: "Orders Created", value: overview?.orders_created ?? 0 },
  ];

  return (
    <main className="min-h-screen bg-slate-950 px-4 py-8 text-white sm:px-8 lg:px-12">
      <div className="mx-auto max-w-7xl">
        <header className="mb-8 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-400">
              Restaurant Analytics
            </p>
            <h1 className="mt-2 text-3xl font-bold sm:text-4xl">
              Analytics Dashboard
            </h1>
            <p className="mt-2 text-slate-400">
              Understand traffic, visitor activity, and orders.
            </p>
          </div>

          <a
            href="/"
            className="rounded-lg border border-slate-700 px-4 py-2 text-sm hover:bg-slate-800"
          >
            ← Restaurant Demo
          </a>
        </header>

        <section className="mb-8 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-5">
          <label className="grid gap-2 text-sm text-slate-300">
            From
            <input
              type="date"
              value={from}
              max={to}
              onChange={(event) => setFrom(event.target.value)}
              className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            />
          </label>

          <label className="grid gap-2 text-sm text-slate-300">
            To
            <input
              type="date"
              value={to}
              min={from}
              onChange={(event) => setTo(event.target.value)}
              className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            />
          </label>

          <button
            onClick={() => setRefresh((value) => value + 1)}
            disabled={loading}
            className="rounded-lg bg-cyan-500 px-5 py-2.5 font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-50"
          >
            {loading ? "Loading..." : "Refresh"}
          </button>

          <div className="ml-auto flex gap-2">
            <button
              onClick={() => {
                setFrom(dateOffset(-6));
                setTo(dateOffset(0));
              }}
              className="rounded-lg border border-slate-700 px-3 py-2 text-sm hover:bg-slate-800"
            >
              7 days
            </button>
            <button
              onClick={() => {
                setFrom(dateOffset(-29));
                setTo(dateOffset(0));
              }}
              className="rounded-lg border border-slate-700 px-3 py-2 text-sm hover:bg-slate-800"
            >
              30 days
            </button>
          </div>
        </section>

        {error && (
          <div className="mb-6 rounded-xl border border-red-800 bg-red-950/40 p-4 text-red-300">
            {error}
          </div>
        )}

        {loading && !overview && (
          <p className="mb-6 text-slate-400">Loading analytics...</p>
        )}

        {overview && !error && (
          <>
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              {metrics.map((metric) => (
                <article
                  key={metric.label}
                  className="rounded-2xl border border-slate-800 bg-slate-900 p-6"
                >
                  <p className="text-sm text-slate-400">{metric.label}</p>
                  <p className="mt-4 text-3xl font-bold tabular-nums">
                    {metric.value.toLocaleString()}
                  </p>
                </article>
              ))}
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-3">
              <article className="rounded-2xl border border-slate-800 bg-slate-900 p-6 xl:col-span-2">
                <h2 className="text-lg font-semibold">Event Trends</h2>
                <p className="mt-1 text-sm text-slate-400">
                  Daily events and page views
                </p>

                {trends.length === 0 ? (
                  <p className="mt-8 text-sm text-slate-400">
                    No events found for this period.
                  </p>
                ) : (
                  <div className="mt-6 space-y-5">
                    {trends.map((item) => (
                      <div key={item.date}>
                        <div className="mb-2 flex justify-between text-sm">
                          <span className="text-slate-300">
                            {formatDate(item.date)}
                          </span>
                          <span className="text-slate-400">
                            {item.total_events} events · {item.page_views} views
                          </span>
                        </div>

                        <div className="h-3 overflow-hidden rounded-full bg-slate-800">
                          <div
                            className="h-full rounded-full bg-cyan-400"
                            style={{
                              width: `${(item.total_events / maxEvents) * 100}%`,
                            }}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </article>

              <article className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 className="text-lg font-semibold">Popular Pages</h2>
                <p className="mt-1 text-sm text-slate-400">
                  Ranked by page views
                </p>

                {pages.length === 0 ? (
                  <p className="mt-8 text-sm text-slate-400">
                    No page views found.
                  </p>
                ) : (
                  <div className="mt-6 space-y-5">
                    {pages.map((page) => (
                      <div key={page.page_url}>
                        <div className="mb-2 flex justify-between gap-3 text-sm">
                          <span
                            className="min-w-0 truncate text-slate-300"
                            title={page.page_url}
                          >
                            {page.page_url}
                          </span>
                          <span className="font-semibold tabular-nums">
                            {page.views}
                          </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-slate-800">
                          <div
                            className="h-full rounded-full bg-violet-400"
                            style={{
                              width: `${(page.views / maxPageViews) * 100}%`,
                            }}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </article>
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
              <div className="border-b border-slate-800 p-6">
                <h2 className="text-lg font-semibold">Recent Events</h2>
                <p className="mt-1 text-sm text-slate-400">
                  Latest activity returned by the API
                </p>
              </div>

              {events.length === 0 ? (
                <p className="p-6 text-sm text-slate-400">
                  No events found for this period.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-950/60 text-slate-400">
                      <tr>
                        <th className="px-6 py-4 font-medium">Event</th>
                        <th className="px-6 py-4 font-medium">Source</th>
                        <th className="px-6 py-4 font-medium">Visitor</th>
                        <th className="px-6 py-4 font-medium">Page</th>
                        <th className="px-6 py-4 font-medium">Time</th>
                      </tr>
                    </thead>
                    <tbody>
                      {events.map((event) => (
                        <tr
                          key={event.id}
                          className="border-t border-slate-800"
                        >
                          <td className="whitespace-nowrap px-6 py-4 font-medium">
                            {event.event_name}
                          </td>
                          <td className="px-6 py-4">
                            <span
                              className={`rounded-full px-2.5 py-1 text-xs ${
                                event.event_source === "server"
                                  ? "bg-violet-950 text-violet-300"
                                  : "bg-cyan-950 text-cyan-300"
                              }`}
                            >
                              {event.event_source}
                            </span>
                          </td>
                          <td className="max-w-36 truncate px-6 py-4 text-slate-400">
                            {event.visitor_id || "—"}
                          </td>
                          <td className="max-w-48 truncate px-6 py-4 text-slate-400">
                            {event.page_url || "—"}
                          </td>
                          <td className="whitespace-nowrap px-6 py-4 text-slate-400">
                            {new Date(event.occurred_at).toLocaleString()}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </>
        )}
      </div>
    </main>
  );
}
