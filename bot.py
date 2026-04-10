import pymongo
import logging
import requests
import os
import random
import asyncio
import threading
from flask import Flask
from datetime import datetime, timedelta
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import (
    ApplicationBuilder,
    CommandHandler,
    ContextTypes,
    CallbackQueryHandler,
    MessageHandler,
    filters
)
from bson import ObjectId
from quiz_data import QUIZ_DATA

# --- WEB SERVER (KEEP-ALIVE) ---
app = Flask(__name__)

@app.route('/')
def home():
    return "Bot is running 24/7!"

def run_flask():
    port = int(os.environ.get("PORT", 8080))
    app.run(host="0.0.0.0", port=port)

# --- CONFIGURATION ---
TOKEN = os.environ.get('TOKEN')
MONGO_URI = os.environ.get('MONGO_URI')
ADMIN_ID = 7281428723
ADEXIUM_WID = '9366eef0-d0ec-430e-ab2f-8c4c87b9fb87'
MENU_VIDEO_URL = 'https://t.me/Realdasturlash/150'

# Logging
logging.basicConfig(format='%(asctime)s - %(name)s - %(levelname)s - %(message)s', level=logging.INFO)

# --- MONGODB ---
client = None; db = None; users_col = None; tasks_col = None; withdraws_col = None; channels_col = None; settings_col = None; promocodes_col = None

if MONGO_URI:
    try:
        client = pymongo.MongoClient(MONGO_URI); db = client['stars_bot_db']
        users_col = db['users']; tasks_col = db['tasks']; withdraws_col = db['withdrawals']
        channels_col = db['channels']; settings_col = db['settings']; promocodes_col = db['promocodes']
        users_col.create_index("user_id", unique=True); logging.info("Connected to MongoDB!")
    except Exception as e: logging.error(f"MongoDB Error: {e}")

# --- ADEXIUM ADS ---
def get_adexium_ad(user_id, first_name):
    try:
        url = 'https://bid.tgads.live/bot-request'
        data = {'wid': ADEXIUM_WID, 'language': 'uz', 'isPremium': False, 'firstName': first_name or 'User', 'telegramId': str(user_id)}
        resp = requests.post(url, json=data, timeout=5)
        if resp.status_code == 200:
            ads = resp.json()
            if isinstance(ads, list) and len(ads) > 0: return ads[0]
            elif isinstance(ads, dict): return ads
    except: pass
    return None

async def send_ad(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user = update.effective_user; ad = get_adexium_ad(user.id, user.first_name)
    if ad and all(k in ad for k in ['text', 'clickUrl', 'buttonText']):
        kb = [[InlineKeyboardButton(ad['buttonText'], url=ad['clickUrl'])]]
        try:
            if 'image' in ad and ad['image'].startswith('http'): await context.bot.send_photo(chat_id=user.id, photo=ad['image'], caption=ad['text'], reply_markup=InlineKeyboardMarkup(kb))
            else: await context.bot.send_message(chat_id=user.id, text=ad['text'], reply_markup=InlineKeyboardMarkup(kb))
        except: pass

# --- HELPERS ---
def get_user(user_id): return users_col.find_one({'user_id': user_id})
def is_admin(user_id): return user_id == ADMIN_ID
def is_maint():
    s = settings_col.find_one({'key': 'maintenance'})
    return s['value'] if s else False

async def check_subs(user_id, context: ContextTypes.DEFAULT_TYPE):
    if is_admin(user_id): return True
    for ch in list(channels_col.find({'type': 'mandatory'})):
        try:
            m = await context.bot.get_chat_member(chat_id=ch['channel_id'], user_id=user_id)
            if m.status not in ['member', 'administrator', 'creator']: return False
        except: return False
    return True

async def safe_edit_caption(query, text, kb):
    try: await query.message.edit_caption(caption=text, reply_markup=kb, parse_mode='HTML')
    except: await query.message.reply_text(text, reply_markup=kb, parse_mode='HTML')

# --- KEYBOARDS ---
def get_main_menu():
    return InlineKeyboardMarkup([
        [InlineKeyboardButton("⌛️ Vaqtli mukofot", callback_data="time_reward")],
        [InlineKeyboardButton("⭐ Yulduz ishlash", callback_data="earn_stars")],
        [InlineKeyboardButton("💵 Yulduz yechish", callback_data="withdraw_stars"), InlineKeyboardButton("👤 Profilim", callback_data="profile")],
        [InlineKeyboardButton("📝 Vazifalar", callback_data="tasks_list"), InlineKeyboardButton("🎟️ Promo-kod", callback_data="promo_code")],
        [InlineKeyboardButton("📚 Qo'llanma", callback_data="manual"), InlineKeyboardButton("🏆 Reyting", callback_data="rating")],
    ])

# --- HANDLERS ---
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    u = update.effective_user; user_id = u.id
    if is_maint() and not is_admin(user_id):
        await update.message.reply_text("🚧 <b>Botda texnik ishlar ketmoqda.</b>", parse_mode='HTML'); return

    existing = get_user(user_id); now = datetime.now()
    if not existing:
        users_col.insert_one({'user_id': user_id, 'username': u.username, 'first_name': u.first_name, 'balance': 0.0, 'referrals': 0, 'referrer_id': int(context.args[0]) if context.args and context.args[0].isdigit() else None, 'completed_tasks': [], 'joined_at': now, 'last_seen': now, 'is_active': True})
        rid = int(context.args[0]) if context.args and context.args[0].isdigit() else None
        if rid and rid != user_id: users_col.update_one({'user_id': rid}, {'$inc': {'balance': 3.0, 'referrals': 1}})
    else: users_col.update_one({'user_id': user_id}, {'$set': {'last_seen': now, 'is_active': True}})

    if not await check_subs(user_id, context):
        kb = [[InlineKeyboardButton(f"📣 {c['name']}", url=c['link'])] for c in channels_col.find({'type': 'mandatory'})]
        kb.append([InlineKeyboardButton("✅ Tekshirish", callback_data="check_subscription")])
        await update.message.reply_text("🤖 <b>Obuna bo'ling!</b>", reply_markup=InlineKeyboardMarkup(kb), parse_mode='HTML'); return

    text = f"👋 <b>Salom {u.first_name}!</b>\n\nMenyudan tanlang 👇"
    try: await context.bot.send_video(chat_id=user_id, video=MENU_VIDEO_URL, caption=text, reply_markup=get_main_menu(), parse_mode='HTML')
    except: await update.message.reply_text(text, reply_markup=get_main_menu(), parse_mode='HTML')

async def callback_route(update: Update, context: ContextTypes.DEFAULT_TYPE):
    q = update.callback_query; d = q.data; user_id = update.effective_user.id; user = get_user(user_id)
    if is_maint() and not is_admin(user_id): await q.answer("🚧 Texnik ishlar!", show_alert=True); return
    await q.answer()

    if d == "check_subscription":
        if await check_subs(user_id, context): await q.message.delete(); await start(update, context)
        else: await q.answer("❌ Obuna bo'lmagansiz!", show_alert=True)
    
    elif d == "profile":
        text = f"👤 <b>Profil</b>\nID: <code>{user['user_id']}</code>\n💰 Balans: {user['balance']:.2f} ⭐️\n👥 Do'stlar: {user['referrals']}"
        await safe_edit_caption(q, text, InlineKeyboardMarkup([[InlineKeyboardButton("🎁 Kunlik bonus", callback_data="daily_bonus")], [InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))
        await send_ad(update, context)

    elif d == "daily_bonus":
        now = datetime.now(); last = user.get('last_daily_reward_at')
        if last and (now - last) < timedelta(days=1): await q.answer("❌ Bugun olgansiz!", show_alert=True); return
        users_col.update_one({'user_id': user_id}, {'$inc': {'balance': 1.0}, '$set': {'last_daily_reward_at': now}})
        await q.answer("✅ +1⭐️", show_alert=True); await callback_route(update, context)

    elif d == "tasks_list":
        avail = [t for t in tasks_col.find({'status': 'active'}) if t['id'] not in user.get('completed_tasks', [])]
        if not avail: await safe_edit_caption(q, "⏳ Tez orada vazifalar qo'shiladi!", InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]])); return
        kb = [[InlineKeyboardButton(f"🎁 {t['title']} ({t['reward']}⭐)", callback_data=f"view_task_{t['id']}")] for t in avail]; kb.append([InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")])
        await safe_edit_caption(q, "📝 <b>Vazifalar</b>", InlineKeyboardMarkup(kb)); await send_ad(update, context)

    elif d.startswith("view_task_"):
        tid = d.split("_")[2]; t = tasks_col.find_one({'id': tid})
        kb = [[InlineKeyboardButton("🔗 Bajarish", url=t['target'])], [InlineKeyboardButton("✅ Tekshirish", callback_data=f"verify_task_{tid}")], [InlineKeyboardButton("⬅️ Orqaga", callback_data="tasks_list")]]
        await safe_edit_caption(q, f"📝 {t['title']}\n{t['description']}", InlineKeyboardMarkup(kb))

    elif d.startswith("verify_task_"):
        tid = d.split("_")[2]; t = tasks_col.find_one({'id': tid}); v = False
        if t['type'] == 'channel':
            try:
                m = await context.bot.get_chat_member(chat_id=t['target'], user_id=user_id)
                if m.status in ['member', 'administrator', 'creator']: v = True
            except: pass
        else: v = True
        if v:
            users_col.update_one({'user_id': user_id}, {'$inc': {'balance': t['reward']}, '$push': {'completed_tasks': tid}})
            await q.answer("✅ Bajarildi!", show_alert=True); await callback_route(update, context)
        else: await q.answer("❌ Obuna bo'ling!", show_alert=True)

    elif d == "withdraw_stars":
        today_start = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0); refs = users_col.count_documents({'referrer_id': user_id, 'joined_at': {'$gte': today_start}})
        if refs < 3 and not is_admin(user_id): await q.answer(f"❌ Bugun 3 ta odam taklif qiling! (Sizda: {refs})", show_alert=True); return
        kb = [[InlineKeyboardButton("15⭐", callback_data="wd_15_teddy"), InlineKeyboardButton("25⭐", callback_data="wd_25_box")], [InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]
        await safe_edit_caption(q, f"💰 Balans: {user['balance']:.2f}⭐️\nTanlang:", InlineKeyboardMarkup(kb))

    elif d.startswith("wd_"):
        amt = float(d.split("_")[1]); gift = d.split("_")[2]
        if user['balance'] < amt: await q.answer("❌ Yetarli emas!", show_alert=True); return
        users_col.update_one({'user_id': user_id}, {'$inc': {'balance': -amt}})
        rid = withdraws_col.insert_one({'user_id': user_id, 'username': user.get('username','User'), 'amount': amt, 'gift': gift, 'status': 'pending', 'at': datetime.now()}).inserted_id
        akb = [[InlineKeyboardButton("✅ Tasdiqlash", callback_data=f"appr_wd_{rid}"), InlineKeyboardButton("❌ Rad etish", callback_data=f"rejt_wd_{rid}")]]
        await context.bot.send_message(chat_id=ADMIN_ID, text=f"🆕 Ariza!\nUser: @{user.get('username','User')}\nMiqdor: {amt}\nGift: {gift}", reply_markup=InlineKeyboardMarkup(akb))
        await safe_edit_caption(q, "✅ Arizangiz yuborildi!", get_main_menu())

    elif d.startswith("appr_wd_"):
        if not is_admin(user_id): return
        rid = ObjectId(d.split("_")[2]); w = withdraws_col.find_one({'_id': rid})
        if w and w['status'] == 'pending':
            withdraws_col.update_one({'_id': rid}, {'$set': {'status': 'approved'}})
            await context.bot.send_message(chat_id=w['user_id'], text=f"✅ Arizangiz tasdiqlandi!")
            await q.message.edit_text(f"✅ Ariza #{rid} tasdiqlandi.")

    elif d.startswith("rejt_wd_"):
        if not is_admin(user_id): return
        rid = ObjectId(d.split("_")[2]); w = withdraws_col.find_one({'_id': rid})
        if w and w['status'] == 'pending':
            withdraws_col.update_one({'_id': rid}, {'$set': {'status': 'rejected'}})
            users_col.update_one({'user_id': w['user_id']}, {'$inc': {'balance': w['amount']}})
            await context.bot.send_message(chat_id=w['user_id'], text=f"❌ Arizangiz rad etildi.")
            await q.message.edit_text(f"❌ Ariza #{rid} rad etildi.")

    elif d == "promo_code":
        users_col.update_one({'user_id': user_id}, {'$set': {'step': 'promo'}})
        await safe_edit_caption(q, "🎟️ Promo-kodni yozing:", InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))

    elif d == "time_reward":
        now = datetime.now(); last = user.get('last_quiz_at')
        if last and (now - last) < timedelta(minutes=20): await q.answer("⏳ Kutish kerak!", show_alert=True); return
        q_text, a = random.choice(QUIZ_DATA); users_col.update_one({'user_id': user_id}, {'$set': {'quiz_answer': a, 'step': 'quiz'}})
        await safe_edit_caption(q, f"🧠 {q_text}", InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))

    elif d == "rating":
        top = users_col.find().sort('referrals', -1).limit(10); text = "🏆 <b>TOP 10</b>\n\n"
        for i, u in enumerate(top): text += f"{i+1}. {u.get('first_name', 'User')} — {u.get('referrals', 0)} ta\n"
        await safe_edit_caption(q, text, InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))

    elif d == "manual": await safe_edit_caption(q, "🤖 <b>Bot qo'llanmasi</b>\n\n• Do'stlar: +3⭐️\n• Viktorina: 0.1⭐️", InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))

    elif d == "back_to_menu": await q.message.delete(); await start(update, context)

async def handle_text(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id; user = get_user(user_id); text = update.message.text; step = user.get('step')
    if step == 'quiz':
        if text.strip().lower() == user.get('quiz_answer').lower():
            users_col.update_one({'user_id': user_id}, {'$inc': {'balance': 0.1}, '$set': {'step': None, 'last_quiz_at': datetime.now()}})
            await update.message.reply_text("✅ To'g'ri!", reply_markup=get_main_menu())
        else: await update.message.reply_text("❌ Noto'g'ri.", reply_markup=get_main_menu()); users_col.update_one({'user_id': user_id}, {'$set': {'step': None}})
    elif step == 'promo':
        p = promocodes_col.find_one({'code': text.strip()})
        if p and p['uses'] < p['max'] and user_id not in p.get('used_by', []):
            users_col.update_one({'user_id': user_id}, {'$inc': {'balance': p['amount']}, '$push': {'used_by_promocodes': text.strip()}})
            promocodes_col.update_one({'code': text.strip()}, {'$inc': {'uses': 1}, '$push': {'used_by': user_id}})
            await update.message.reply_text(f"✅ +{p['amount']}⭐️!", reply_markup=get_main_menu())
        else: await update.message.reply_text("❌ Xato yoki ishlatilgan!", reply_markup=get_main_menu())
        users_col.update_one({'user_id': user_id}, {'$set': {'step': None}})

async def admin_panel(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_admin(update.effective_user.id): return
    t = users_col.count_documents({}); a = users_col.count_documents({'last_seen': {'$gt': datetime.now() - timedelta(days=1)}})
    await update.message.reply_text(f"💻 <b>Admin</b>\nUserlar: {t}\nFaol: {a}\n/broadcast - mass\n/set_maintenance\n/add_promo <code> <amt> <max>\n/add_task <id> <title> <reward> <type> <target>")

async def broadcast(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_admin(update.effective_user.id) or not update.message.reply_to_message: return
    msg = update.message.reply_to_message; count = 0
    for u in users_col.find({'is_active': True}):
        try: await context.bot.copy_message(chat_id=u['user_id'], from_chat_id=msg.chat_id, message_id=msg.message_id); count += 1; await asyncio.sleep(0.05)
        except: users_col.update_one({'user_id': u['user_id']}, {'$set': {'is_active': False}})
    await update.message.reply_text(f"✅ {count} ta yuborildi.")

async def maintenance_cmd(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_admin(update.effective_user.id): return
    v = not is_maint(); settings_col.update_one({'key': 'maintenance'}, {'$set': {'value': v}}, upsert=True)
    status_text = "YONIQ" if v else "O'CHIQ"
    await update.message.reply_text(f"🚧 Texnik rejim: {status_text}")

async def add_task(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_admin(update.effective_user.id): return
    try:
        tid, title, reward, ttype, target = context.args[0], context.args[1], float(context.args[2]), context.args[3], context.args[4]
        tasks_col.insert_one({'id': tid, 'title': title, 'reward': reward, 'type': ttype, 'target': target, 'status': 'active'})
        await update.message.reply_text("✅ Vazifa qo'shildi!")
    except: await update.message.reply_text("⚠️ /add_task <id> <title> <reward> <type:channel/link> <target>")

async def add_promo(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_admin(update.effective_user.id): return
    try:
        c, a, m = context.args[0], float(context.args[1]), int(context.args[2])
        promocodes_col.insert_one({'code': c, 'amount': a, 'max': m, 'uses': 0, 'used_by': []})
        await update.message.reply_text("✅ Promo-kod qo'shildi!")
    except: await update.message.reply_text("⚠️ /add_promo <code> <amt> <max>")

if __name__ == '__main__':
    # Start web server for 24/7 (Render health check)
    threading.Thread(target=run_flask, daemon=True).start()
    
    app = ApplicationBuilder().token(TOKEN).build()
    app.add_handler(CommandHandler("start", start)); app.add_handler(CommandHandler("admin", admin_panel)); app.add_handler(CommandHandler("broadcast", broadcast))
    app.add_handler(CommandHandler("set_maintenance", maintenance_cmd)); app.add_handler(CommandHandler("add_promo", add_promo)); app.add_handler(CommandHandler("add_task", add_task))
    app.add_handler(CallbackQueryHandler(callback_route)); app.add_handler(MessageHandler(filters.TEXT & (~filters.COMMAND), handle_text))
    print("Bot started..."); app.run_polling()