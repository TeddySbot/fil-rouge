#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Loge-Moi — Module d'analyse de données (réservé aux agents).

Compétence visée : « Analyse et manipulation de données en Python ».
Le script :
  1. extrait les données de la base MySQL `ymmo_db` (biens, transactions, favoris) ;
  2. nettoie / prépare les données (typage, valeurs aberrantes, doublons) ;
  3. produit des rapports de ventes et des statistiques ;
  4. réalise des prévisions de ventes (régression linéaire) et identifie
     les biens populaires et les zones intéressantes ;
  5. exporte un rapport JSON + des graphiques PNG consommés par
     `agent/analytics.php`.

Usage :
    python analyze.py [--agency-id N] [--config config.json] [--output output]
    python analyze.py --self-test      # exécute le pipeline sur des données synthétiques
"""

import argparse
import json
import os
import sys
from datetime import datetime

import numpy as np
import pandas as pd

# Backend non interactif (le script tourne sans écran, lancé par PHP).
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

# ─────────────────────────── Thème graphique ───────────────────────────
GOLD, GREEN, BLUE, RED = "#d4a843", "#3ecf74", "#5b8def", "#e5484d"
plt.rcParams.update({
    "figure.facecolor": "#16171c",
    "axes.facecolor":   "#16171c",
    "savefig.facecolor": "#16171c",
    "axes.edgecolor":   "#2a2c33",
    "axes.grid":        True,
    "grid.color":       "#23252b",
    "text.color":       "#e8e8ea",
    "axes.labelcolor":  "#b8b9be",
    "axes.titlecolor":  "#e8e8ea",
    "xtick.color":      "#b8b9be",
    "ytick.color":      "#b8b9be",
    "font.size":        10,
    "figure.dpi":       110,
})

TYPE_LABELS = {
    "house": "Maison", "apartment": "Appartement",
    "land": "Terrain", "commercial": "Local commercial",
}


# ─────────────────────────── Extraction ───────────────────────────
def load_config(path):
    with open(path, "r", encoding="utf-8") as fh:
        return json.load(fh)


def fetch_dataframes(cfg, agency_id=None):
    """Récupère les données depuis MySQL et renvoie 3 DataFrames."""
    import pymysql
    conn = pymysql.connect(
        host=cfg.get("host", "localhost"),
        port=int(cfg.get("port", 3306)),
        user=cfg.get("user", "root"),
        password=cfg.get("password", ""),
        database=cfg.get("database", "ymmo_db"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        scope = " WHERE p.agency_id = %s" if agency_id else ""
        args = [agency_id] if agency_id else []

        with conn.cursor() as cur:
            cur.execute(
                "SELECT p.id, p.title, p.price, p.surface, p.city, p.property_type, "
                "p.agent_id, p.agency_id, p.status, p.views_count, p.created_at "
                "FROM properties p" + scope, args)
            properties = cur.fetchall()

            cur.execute(
                "SELECT t.id, t.property_id, t.price, t.transaction_date, t.status, "
                "p.city, p.property_type, p.agency_id "
                "FROM transactions t JOIN properties p ON p.id = t.property_id" + scope, args)
            transactions = cur.fetchall()

            cur.execute(
                "SELECT f.property_id, COUNT(*) AS favorites "
                "FROM favorites f JOIN properties p ON p.id = f.property_id" + scope +
                " GROUP BY f.property_id", args)
            favorites = cur.fetchall()
    finally:
        conn.close()

    return (pd.DataFrame(properties), pd.DataFrame(transactions), pd.DataFrame(favorites))


# ─────────────────────────── Nettoyage ───────────────────────────
def clean_properties(df):
    """Typage, suppression des doublons et des valeurs aberrantes."""
    if df.empty:
        return df
    df = df.drop_duplicates(subset="id").copy()
    df["price"] = pd.to_numeric(df["price"], errors="coerce")
    df["surface"] = pd.to_numeric(df["surface"], errors="coerce")
    df["views_count"] = pd.to_numeric(df["views_count"], errors="coerce").fillna(0).astype(int)
    df["created_at"] = pd.to_datetime(df["created_at"], errors="coerce")
    df["city"] = df["city"].fillna("").astype(str).str.strip().str.title()
    # Prix au m² : seulement pour des valeurs strictement positives.
    df["price_per_m2"] = np.where(
        (df["surface"].fillna(0) > 0) & (df["price"].fillna(0) > 0),
        df["price"] / df["surface"], np.nan)
    return df


def clean_transactions(df):
    if df.empty:
        return df
    df = df.drop_duplicates(subset="id").copy()
    df["price"] = pd.to_numeric(df["price"], errors="coerce")
    df["transaction_date"] = pd.to_datetime(df["transaction_date"], errors="coerce")
    df = df[df["price"].fillna(0) > 0]
    return df


# ─────────────────────────── Analyses ───────────────────────────
def compute_kpis(props, trans):
    completed = trans[trans["status"] == "completed"] if not trans.empty else trans
    return {
        "total_properties": int(len(props)),
        "available":        int((props["status"] == "available").sum()) if not props.empty else 0,
        "sold":             int((props["status"] == "sold").sum()) if not props.empty else 0,
        "rented":           int((props["status"] == "rented").sum()) if not props.empty else 0,
        "total_views":      int(props["views_count"].sum()) if not props.empty else 0,
        "avg_price":        round(float(props["price"].mean()), 2) if not props.empty and props["price"].notna().any() else 0.0,
        "avg_price_per_m2": round(float(props["price_per_m2"].mean()), 2) if not props.empty and props["price_per_m2"].notna().any() else 0.0,
        "completed_sales":  int(len(completed)),
        "total_revenue":    round(float(completed["price"].sum()), 2) if not completed.empty else 0.0,
    }


def sales_by_month(trans):
    """Ventes finalisées agrégées par mois (count + chiffre d'affaires)."""
    completed = trans[trans["status"] == "completed"] if not trans.empty else trans
    if completed.empty:
        return pd.DataFrame(columns=["month", "count", "revenue"])
    g = completed.dropna(subset=["transaction_date"]).copy()
    g["month"] = g["transaction_date"].dt.to_period("M").astype(str)
    out = g.groupby("month").agg(count=("id", "count"), revenue=("price", "sum")).reset_index()
    return out.sort_values("month").reset_index(drop=True)


def forecast_sales(monthly, horizon=3):
    """Prévision par régression linéaire (numpy.polyfit) sur les mois passés."""
    if monthly.empty or len(monthly) < 2:
        return {"method": "indisponible",
                "reason": "Au moins 2 mois de ventes sont nécessaires.",
                "next_months": []}

    x = np.arange(len(monthly))
    coeffs_cnt = np.polyfit(x, monthly["count"].to_numpy(dtype=float), 1)
    coeffs_rev = np.polyfit(x, monthly["revenue"].to_numpy(dtype=float), 1)

    last = pd.Period(monthly["month"].iloc[-1], freq="M")
    preds = []
    for i in range(1, horizon + 1):
        xi = len(monthly) - 1 + i
        preds.append({
            "month": str(last + i),
            "predicted_sales": max(0, round(float(np.polyval(coeffs_cnt, xi)))),
            "predicted_revenue": max(0.0, round(float(np.polyval(coeffs_rev, xi)), 2)),
        })
    trend = "hausse" if coeffs_cnt[0] > 0 else ("baisse" if coeffs_cnt[0] < 0 else "stable")
    return {"method": "régression linéaire", "trend": trend, "next_months": preds}


def popular_properties(props, favs, top=5):
    if props.empty:
        return [], []
    by_views = (props.sort_values("views_count", ascending=False).head(top)
                [["id", "title", "city", "views_count"]]
                .rename(columns={"views_count": "views"}).to_dict("records"))

    merged = props.copy()
    if favs is not None and not favs.empty:
        merged = merged.merge(favs, left_on="id", right_on="property_id", how="left")
    else:
        merged["favorites"] = 0
    merged["favorites"] = merged.get("favorites", 0)
    merged["favorites"] = pd.to_numeric(merged["favorites"], errors="coerce").fillna(0).astype(int)
    by_favs = (merged.sort_values("favorites", ascending=False).head(top)
               [["id", "title", "city", "favorites"]].to_dict("records"))
    return by_views, by_favs


def interesting_zones(props, min_count=1):
    """Zones (villes) par prix au m², triées du moins cher au plus cher."""
    if props.empty or props["price_per_m2"].notna().sum() == 0:
        return []
    valid = props.dropna(subset=["price_per_m2"])
    g = valid.groupby("city").agg(
        avg_price_m2=("price_per_m2", "mean"),
        median_price_m2=("price_per_m2", "median"),
        avg_price=("price", "mean"),
        count=("id", "count"),
    ).reset_index()
    g = g[g["count"] >= min_count].sort_values("avg_price_m2")
    g[["avg_price_m2", "median_price_m2", "avg_price"]] = g[
        ["avg_price_m2", "median_price_m2", "avg_price"]].round(2)
    return g.to_dict("records")


def type_distribution(props):
    if props.empty:
        return []
    g = props.groupby("property_type").size().reset_index(name="count")
    return [{"type": r["property_type"],
             "label": TYPE_LABELS.get(r["property_type"], r["property_type"]),
             "count": int(r["count"])} for _, r in g.iterrows()]


# ─────────────────────────── Graphiques ───────────────────────────
def _save(fig, path):
    fig.tight_layout()
    fig.savefig(path, bbox_inches="tight")
    plt.close(fig)


def chart_sales(monthly, forecast, outdir):
    if monthly.empty:
        return None
    fig, ax = plt.subplots(figsize=(7, 3.6))
    months = list(monthly["month"])
    ax.bar(months, monthly["revenue"], color=GOLD, alpha=.85, label="CA réalisé")
    fc = forecast.get("next_months", [])
    if fc:
        fmonths = [p["month"] for p in fc]
        frev = [p["predicted_revenue"] for p in fc]
        ax.bar(fmonths, frev, color=GREEN, alpha=.55, label="CA prévu")
    ax.set_title("Chiffre d'affaires par mois (réalisé + prévision)")
    ax.set_ylabel("€")
    ax.legend(facecolor="#16171c", edgecolor="#2a2c33")
    plt.setp(ax.get_xticklabels(), rotation=45, ha="right")
    path = os.path.join(outdir, "sales_monthly.png")
    _save(fig, path)
    return "sales_monthly.png"


def chart_zones(zones, outdir):
    if not zones:
        return None
    z = zones[:10]
    fig, ax = plt.subplots(figsize=(7, 3.6))
    cities = [r["city"] or "—" for r in z]
    ax.barh(cities, [r["avg_price_m2"] for r in z], color=BLUE, alpha=.85)
    ax.invert_yaxis()
    ax.set_title("Prix moyen au m² par ville (croissant)")
    ax.set_xlabel("€ / m²")
    path = os.path.join(outdir, "price_per_m2_city.png")
    _save(fig, path)
    return "price_per_m2_city.png"


def chart_types(dist, outdir):
    if not dist:
        return None
    fig, ax = plt.subplots(figsize=(4.6, 3.6))
    ax.pie([d["count"] for d in dist], labels=[d["label"] for d in dist],
           autopct="%1.0f%%", colors=[GOLD, GREEN, BLUE, RED][:len(dist)],
           textprops={"color": "#e8e8ea"})
    ax.set_title("Répartition par type de bien")
    path = os.path.join(outdir, "type_distribution.png")
    _save(fig, path)
    return "type_distribution.png"


def chart_top_views(by_views, outdir):
    if not by_views:
        return None
    fig, ax = plt.subplots(figsize=(7, 3.2))
    titles = [(r["title"][:24] + "…") if len(r["title"]) > 25 else r["title"] for r in by_views][::-1]
    ax.barh(titles, [r["views"] for r in by_views][::-1], color=GOLD, alpha=.85)
    ax.set_title("Biens les plus consultés")
    ax.set_xlabel("Vues")
    path = os.path.join(outdir, "top_views.png")
    _save(fig, path)
    return "top_views.png"


# ─────────────────────────── Pipeline ───────────────────────────
def run_pipeline(props_raw, trans_raw, favs_raw, outdir, scope):
    os.makedirs(outdir, exist_ok=True)
    props = clean_properties(props_raw)
    trans = clean_transactions(trans_raw)
    favs = favs_raw

    warnings = []
    if props.empty:
        warnings.append("Aucun bien trouvé pour ce périmètre.")
    if trans.empty:
        warnings.append("Aucune transaction : rapports de ventes et prévisions limités.")

    monthly = sales_by_month(trans)
    forecast = forecast_sales(monthly)
    by_views, by_favs = popular_properties(props, favs)
    zones = interesting_zones(props)
    dist = type_distribution(props)

    charts = {
        "sales_monthly":     chart_sales(monthly, forecast, outdir),
        "price_per_m2_city": chart_zones(zones, outdir),
        "type_distribution": chart_types(dist, outdir),
        "top_views":         chart_top_views(by_views, outdir),
    }

    report = {
        "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "scope": scope,
        "data_counts": {
            "properties": int(len(props)),
            "transactions": int(len(trans)),
            "favorites": int(0 if favs is None or favs.empty else favs["favorites"].sum()),
        },
        "warnings": warnings,
        "kpis": compute_kpis(props, trans),
        "sales_by_month": monthly.to_dict("records"),
        "forecast": forecast,
        "popular_by_views": by_views,
        "popular_by_favorites": by_favs,
        "zones": zones,
        "type_distribution": dist,
        "charts": {k: v for k, v in charts.items() if v},
    }

    with open(os.path.join(outdir, "report.json"), "w", encoding="utf-8") as fh:
        json.dump(report, fh, ensure_ascii=False, indent=2, default=str)
    return report


# ─────────────────────────── Données synthétiques (self-test) ───────────────────────────
def _synthetic_data():
    rng = np.random.default_rng(42)
    cities = ["Paris", "Lyon", "Marseille", "Lille", "Nantes"]
    types = ["house", "apartment", "land", "commercial"]
    props = []
    for i in range(1, 61):
        city = cities[i % len(cities)]
        surface = int(rng.integers(25, 200))
        base_m2 = {"Paris": 9500, "Lyon": 4800, "Marseille": 3200, "Lille": 3000, "Nantes": 3600}[city]
        price = round(surface * base_m2 * float(rng.uniform(0.8, 1.2)), 2)
        props.append({
            "id": i, "title": f"Bien {i}", "price": price, "surface": surface,
            "city": city, "property_type": types[i % len(types)],
            "agent_id": 1, "agency_id": 1,
            "status": ["available", "available", "sold", "rented"][i % 4],
            "views_count": int(rng.integers(0, 500)),
            "created_at": f"2026-0{(i % 6) + 1}-15 10:00:00",
        })
    trans = []
    tid = 1
    for m in range(1, 7):  # 6 mois de ventes croissantes
        for _ in range(m + 2):
            pid = int(rng.integers(1, 61))
            trans.append({
                "id": tid, "property_id": pid,
                "price": round(float(rng.uniform(120000, 900000)), 2),
                "transaction_date": f"2026-0{m}-{int(rng.integers(1,28)):02d} 12:00:00",
                "status": "completed", "city": "Paris",
                "property_type": "apartment", "agency_id": 1,
            })
            tid += 1
    favs = [{"property_id": int(rng.integers(1, 61)), "favorites": int(rng.integers(1, 12))}
            for _ in range(20)]
    return pd.DataFrame(props), pd.DataFrame(trans), pd.DataFrame(favs)


# ─────────────────────────── main ───────────────────────────
def main():
    parser = argparse.ArgumentParser(description="Analyse de données Loge-Moi (agents).")
    parser.add_argument("--agency-id", type=int, default=None)
    parser.add_argument("--config", default=os.path.join(os.path.dirname(__file__), "config.json"))
    parser.add_argument("--output", default=os.path.join(os.path.dirname(__file__), "output"))
    parser.add_argument("--self-test", action="store_true",
                        help="Exécute le pipeline sur des données synthétiques (sans base).")
    args = parser.parse_args()

    scope = {"agency_id": args.agency_id, "agency_name": None}

    try:
        if args.self_test:
            props_raw, trans_raw, favs_raw = _synthetic_data()
        else:
            cfg = load_config(args.config)
            props_raw, trans_raw, favs_raw = fetch_dataframes(cfg, args.agency_id)

        report = run_pipeline(props_raw, trans_raw, favs_raw, args.output, scope)
        print(json.dumps({"success": True,
                          "report": os.path.join(args.output, "report.json"),
                          "counts": report["data_counts"]}, ensure_ascii=False))
        return 0
    except Exception as exc:  # noqa: BLE001 — on remonte l'erreur en JSON pour PHP
        os.makedirs(args.output, exist_ok=True)
        err = {"success": False, "error": str(exc), "type": type(exc).__name__,
               "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
        with open(os.path.join(args.output, "report.json"), "w", encoding="utf-8") as fh:
            json.dump(err, fh, ensure_ascii=False, indent=2)
        print(json.dumps(err, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    sys.exit(main())
