
"use client";

import { useEffect, useState } from "react";
import { Analytics } from "@restaurant-analytics/browser-sdk";

const menu = [
  { id: "item-101", name: "Paneer Pizza", category: "Pizza", price: 249, emoji: "🍕" },
  { id: "item-102", name: "Veg Burger", category: "Burgers", price: 149, emoji: "🍔" },
  { id: "item-103", name: "Masala Dosa", category: "South Indian", price: 99, emoji: "🥞" },
  { id: "item-104", name: "Veg Biryani", category: "Rice", price: 179, emoji: "🍛" },
  { id: "item-105", name: "Fresh Lime Soda", category: "Drinks", price: 69, emoji: "🍋" },
  { id: "item-106", name: "Chocolate Brownie", category: "Desserts", price: 119, emoji: "🍫" },
];

export default function Home() {
  const [analytics, setAnalytics] = useState<Analytics | null>(null);
  const [cart, setCart] = useState<Record<string, number>>({});
  const [message, setMessage] = useState("");

  useEffect(() => {
    const endpoint = process.env.NEXT_PUBLIC_ANALYTICS_URL;
    const projectKey = process.env.NEXT_PUBLIC_ANALYTICS_KEY;

    if (!endpoint || !projectKey) {
      console.error("Analytics environment variables are missing.");
      return;
    }

    const client = new Analytics({ endpoint, projectKey });
    setAnalytics(client);
    client.trackPageView();
  }, []);

  const addToCart = (item: (typeof menu)[number]) => {
    setCart((current) => ({
      ...current,
      [item.id]: (current[item.id] ?? 0) + 1,
    }));

    analytics?.track("menu_item_added", {
      item_id: item.id,
      item_name: item.name,
      price: item.price,
    });

    setMessage(`${item.name} added to your cart.`);
  };

  const cartCount = Object.values(cart).reduce(
    (total, quantity) => total + quantity,
    0
  );

  const total = menu.reduce(
    (sum, item) => sum + item.price * (cart[item.id] ?? 0),
    0
  );

  const placeDemoOrder = async () => {
    if (cartCount === 0) {
      setMessage("Add an item before placing your demo order.");
      return;
    }
  
    const endpoint = process.env.NEXT_PUBLIC_ANALYTICS_URL;
  
    if (!endpoint) {
      setMessage("The order service URL is not configured.");
      return;
    }
  
    setMessage("Creating your order...");
  
    try {
      const response = await fetch(
        `${endpoint.replace(/\/$/, "")}/api/v1/orders`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            items: Object.entries(cart)
              .filter(([, quantity]) => quantity > 0)
              .map(([item_id, quantity]) => ({
                item_id,
                quantity,
              })),
          }),
        }
      );
  
      const result = await response.json();
  
      if (!response.ok) {
        throw new Error(
          result.message || `Order failed with status ${response.status}.`
        );
      }
  
      setMessage(
        `Order ${result.order.order_number} created successfully!`
      );
  
      // Browser interaction event only; order_created is sent by Laravel.
      analytics?.track("checkout_completed", {
        order_number: result.order.order_number,
        total_amount: result.order.total_amount,
        currency: result.order.currency,
      });
  
      setCart({});
    } catch (error) {
      setMessage(
        error instanceof Error
          ? error.message
          : "Unable to create your order."
      );
    }
  };

  return (
    <main className="min-h-screen bg-stone-50 text-stone-900">
      <header className="border-b border-stone-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
          <div>
            <p className="text-xl font-bold">Spice & Slice</p>
            <p className="text-sm text-stone-500">Fresh food, made with care</p>
          </div>
          <div className="flex items-center gap-3">
            <a
              href="/dashboard"
              className="text-sm font-semibold text-stone-600 hover:text-orange-700"
            >
              Analytics dashboard
            </a>
            <div className="rounded-full bg-orange-100 px-4 py-2 text-sm font-semibold text-orange-800">
              🛒 Cart: {cartCount}
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto max-w-6xl px-5 py-12">
        <div className="rounded-3xl bg-stone-900 px-7 py-12 text-white sm:px-12">
          <p className="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-orange-300">
            Restaurant Demo
          </p>
          <h1 className="text-4xl font-bold sm:text-5xl">
            Good food. Great mood.
          </h1>
          <p className="mt-4 text-stone-300">
            Explore the menu, add your favourites, and try our demo checkout.
          </p>
        </div>

        <div className="mb-6 mt-10">
          <h2 className="text-2xl font-bold">Our menu</h2>
          <p className="mt-1 text-sm text-stone-500">
            Every interaction can generate an analytics event.
          </p>
        </div>

        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {menu.map((item) => (
            <article
              key={item.id}
              className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
            >
              <div className="mb-5 flex h-28 items-center justify-center rounded-xl bg-orange-50 text-6xl">
                {item.emoji}
              </div>
              <p className="text-xs font-semibold uppercase tracking-wider text-orange-700">
                {item.category}
              </p>
              <div className="mt-1 flex items-center justify-between gap-3">
                <h3 className="text-lg font-bold">{item.name}</h3>
                <span className="font-semibold">₹{item.price}</span>
              </div>
              <button
                onClick={() => addToCart(item)}
                className="mt-5 w-full rounded-xl bg-stone-900 px-4 py-3 text-sm font-semibold text-white hover:bg-orange-700"
              >
                Add to cart
              </button>
            </article>
          ))}
        </div>

        <section className="mt-10 rounded-2xl border border-stone-200 bg-white p-6">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 className="text-xl font-bold">Your order</h2>
              <p className="mt-1 text-sm text-stone-500">
                {cartCount} item(s) · Total ₹{total}
              </p>
            </div>
            <button
              onClick={placeDemoOrder}
              className="rounded-xl bg-orange-600 px-6 py-3 font-semibold text-white hover:bg-orange-700"
            >
              Continue to checkout
            </button>
          </div>
          {message && (
            <p aria-live="polite" className="mt-4 text-sm text-green-700">
              {message}
            </p>
          )}
        </section>
      </section>

      <footer className="border-t border-stone-200 bg-white px-5 py-6 text-center text-sm text-stone-500">
        Restaurant Analytics Demo · Built with Next.js
      </footer>
    </main>
  );
}
