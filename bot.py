import pymongo
import logging
import os
import threading
from flask import Flask
from datetime import datetime
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup, LabeledPrice
from telegram.ext import (
    ApplicationBuilder,
    CommandHandler,
    ContextTypes,
    CallbackQueryHandler,
    MessageHandler,
    filters,
    ConversationHandler,
    PreCheckoutQueryHandler
)

# --- WEB SERVER (KEEP-ALIVE) ---
app = Flask(__name__)

@app.route('/')
def home():
    return "Bot is running 24/7!"

def run_flask():
    port = int(os.environ.get("PORT", 8080))
    app.run(host="0.0.0.0", port=port)

# --- CONFIGURATION ---
TOKEN = os.environ.get('TOKEN', '8222893594:AAEd3osaQS7FIOjLJ9nsLwg2aCQ7vipWvoU')
MONGO_URI = os.environ.get('MONGO_URI')
ADMIN_ID = 7281428723

logging.basicConfig(format='%(asctime)s - %(name)s - %(levelname)s - %(message)s', level=logging.INFO)

# --- MONGODB ---
client = None; db = None; users_col = None; movies_col = None; counters_col = None

if MONGO_URI:
    try:
        client = pymongo.MongoClient(MONGO_URI); db = client['movie_bot_db']
        users_col = db['users']
        movies_col = db['movies']
        counters_col = db['counters']
        users_col.create_index("user_id", unique=True); logging.info("Connected to MongoDB!")
    except Exception as e: logging.error(f"MongoDB Error: {e}")

# Admin Conversation States
A_TITLE = 1
A_YEAR = 2
A_GENRE = 3
A_TYPE = 4
A_PRICE = 5
A_CONFIRM = 6

# Functions
def get_next_movie_id():
    if not counters_col: return 1
    doc = counters_col.find_one_and_update(
        {'_id': 'movie_id'},
        {'$inc': {'seq': 1}},
        upsert=True,
        return_document=pymongo.ReturnDocument.AFTER
    )
    return doc['seq']

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user = update.effective_user
    user_id = user.id
    
    if users_col is not None:
        existing = users_col.find_one({'user_id': user_id})
        if not existing:
            users_col.insert_one({
                'user_id': user_id,
                'username': user.username,
                'first_name': user.first_name,
                'purchased_movies': [],
                'joined_at': datetime.now()
            })
            msg = f"👋 Assalomu alaykum, {user.first_name}!\n\nSiz botdan muvaffaqiyatli ro'yxatdan o'tdingiz (Sign Up)."
        else:
            msg = f"👋 Qaytganingiz bilan, {user.first_name}!\n\nSiz tizimga kirdingiz (Sign In)."
    else:
        msg = f"👋 Assalomu alaykum, {user.first_name}! (Local rejim, bazaga ulanmagan)"
        
    kb = [
        [InlineKeyboardButton("🔍 Kino Qidirish", callback_data="search_movie")],
        [InlineKeyboardButton("👤 Profilim", callback_data="profile")]
    ]
    if user_id == ADMIN_ID:
        kb.append([InlineKeyboardButton("👑 Admin Panel", callback_data="admin_panel")])
        
    if update.message:
        await update.message.reply_text(msg, reply_markup=InlineKeyboardMarkup(kb))
    else:
        await update.callback_query.message.edit_text(msg, reply_markup=InlineKeyboardMarkup(kb))

async def callback_route(update: Update, context: ContextTypes.DEFAULT_TYPE):
    q = update.callback_query
    d = q.data
    user_id = q.from_user.id
    await q.answer()

    if d == "back_to_menu":
        await start(update, context)
        
    elif d == "search_movie":
        await q.message.edit_text(
            "🔍 Qidirmoqchi bo'lgan kinoning id rakamini yoki nomini yozib yuboring.",
            reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]])
        )
        context.user_data['step'] = 'search'
        
    elif d == "profile":
        u = users_col.find_one({'user_id': user_id}) if users_col is not None else None
        purchased = len(u.get('purchased_movies', [])) if u else 0
        text = f"👤 <b>Profilim</b>\n\nID: <code>{user_id}</code>\nXarid qilingan kinolar: {purchased} ta"
        await q.message.edit_text(text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))
        
    elif d == "admin_panel":
        if user_id != ADMIN_ID: return
        await q.message.edit_text("👑 Admin Panel!\nKino qo'shish uchun shunchaki botga istalgan kinoni yuboring (<b>Video</b>).", parse_mode='HTML', reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("⬅️ Orqaga", callback_data="back_to_menu")]]))
        
    elif d.startswith("buy_"):
        movie_id = int(d.split("_")[1])
        if movies_col is None: return
        movie = movies_col.find_one({'id': movie_id})
        if not movie:
            await q.message.reply_text("Kino topilmadi.")
            return
            
        stars_price = movie.get('price', 1)
        title = movie['title']
        description = f"{title} kinosini sotib olish."
        payload = f"movie_{movie_id}"
        currency = "XTR"
        prices = [LabeledPrice("Kino", stars_price)]
        
        await context.bot.send_invoice(
            chat_id=user_id,
            title=title,
            description=description,
            payload=payload,
            provider_token="", # stars uchun bo'sh
            currency=currency,
            prices=prices,
        )

# PreCheckout
async def precheckout_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.pre_checkout_query
    if query.invoice_payload.startswith("movie_"):
        await query.answer(ok=True)
    else:
        await query.answer(ok=False, error_message="Xatolik yuz berdi.")

# Successful Payment
async def successful_payment_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    payment = update.message.successful_payment
    payload = payment.invoice_payload
    user_id = update.message.from_user.id
    
    if payload.startswith("movie_"):
        movie_id = int(payload.split("_")[1])
        
        if users_col is not None and movies_col is not None:
            users_col.update_one({'user_id': user_id}, {'$addToSet': {'purchased_movies': movie_id}})
            movie = movies_col.find_one({'id': movie_id})
            if movie:
                await update.message.reply_video(video=movie['file_id'], caption=f"🎬 <b>{movie['title']}</b>\n\n🎉 Xaridingiz uchun rahmat!", parse_mode='HTML')

# Search handler
async def handle_text(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    text = update.message.text
    
    step = context.user_data.get('step')
    if step == 'search':
        if movies_col is None:
            await update.message.reply_text("Ma'lumotlar bazasi topilmadi (MongoDB ishlamayapti).")
            return
            
        # Try ID first
        if text.isdigit():
            movies = list(movies_col.find({'id': int(text)}))
        else:
            movies = list(movies_col.find({'title': {'$regex': text, '$options': 'i'}}).limit(10))
            
        if not movies:
            await update.message.reply_text("❌ Kino topilmadi.", reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("🔍 Qayta qidirish", callback_data="search_movie"), InlineKeyboardButton("⬅️ Asosiy", callback_data="back_to_menu")]]))
            return
            
        for m in movies:
            caption = f"🎬 <b>{m['title']}</b>\n📅 Yil: {m['year']}\n🎭 Janr: {m['genre']}\nID: <code>{m['id']}</code>"
            kb = []
            
            # Check if user already purchased
            u = users_col.find_one({'user_id': user_id})
            purchased = u and m['id'] in u.get('purchased_movies', [])
            
            if m['type'] == 'free' or purchased or user_id == ADMIN_ID:
                await update.message.reply_video(video=m['file_id'], caption=caption, parse_mode='HTML')
            else:
                caption += f"\n\n💰 Narxi: {m['price']} ⭐\nKino pullik (Premium)."
                kb.append([InlineKeyboardButton(f"Sotib Olish ({m['price']}⭐)", callback_data=f"buy_{m['id']}")])
                await update.message.reply_text(caption, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(kb))
                
        context.user_data['step'] = None

# ---- Admin Conversation Logic ----
async def admin_video_receive(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if update.effective_user.id != ADMIN_ID:
        return
    file_id = update.message.video.file_id
    context.user_data['admin_file_id'] = file_id
    await update.message.reply_text("Kino nomini kiriting:")
    return A_TITLE

async def admin_title(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data['admin_title'] = update.message.text
    await update.message.reply_text("Yilini kiriting (masalan, 2024):")
    return A_YEAR

async def admin_year(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data['admin_year'] = update.message.text
    await update.message.reply_text("Janrini kiriting (masalan, Jangari, Fantastika):")
    return A_GENRE

async def admin_genre(update: Update, context: ContextTypes.DEFAULT_TYPE):
    context.user_data['admin_genre'] = update.message.text
    from telegram import ReplyKeyboardMarkup
    kb = [["Free", "Paid"]]
    await update.message.reply_text("Kino turi qanday?", reply_markup=ReplyKeyboardMarkup(kb, one_time_keyboard=True, resize_keyboard=True))
    return A_TYPE

async def admin_type(update: Update, context: ContextTypes.DEFAULT_TYPE):
    t = update.message.text.lower()
    if t not in ['free', 'paid']:
        await update.message.reply_text("Iltimos 'Free' yoki 'Paid' deb yozing.")
        return A_TYPE
    context.user_data['admin_type'] = t
    from telegram import ReplyKeyboardRemove
    if t == 'paid':
        await update.message.reply_text("Narxini yulduz tugmalarida (Stars/⭐) kiriting (masalan: 15):", reply_markup=ReplyKeyboardRemove())
        return A_PRICE
    else:
        context.user_data['admin_price'] = 0
        return await ask_confirm(update, context)

async def admin_price(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not update.message.text.isdigit():
        await update.message.reply_text("Faqat raqam kiriting:")
        return A_PRICE
    context.user_data['admin_price'] = int(update.message.text)
    return await ask_confirm(update, context)

async def ask_confirm(update: Update, context: ContextTypes.DEFAULT_TYPE):
    from telegram import ReplyKeyboardMarkup
    ud = context.user_data
    text = (
        f"✅ <b>Kino ma'lumotlari:</b>\n"
        f"Nomi: {ud['admin_title']}\n"
        f"Yil: {ud['admin_year']}\n"
        f"Janr: {ud['admin_genre']}\n"
        f"Turi: {ud['admin_type']}\n"
        f"Narxi: {ud['admin_price']} ⭐\n\n"
        "Saqlansinmi?"
    )
    kb = [["Ha", "Yo'q"]]
    await update.message.reply_text(text, parse_mode='HTML', reply_markup=ReplyKeyboardMarkup(kb, one_time_keyboard=True, resize_keyboard=True))
    return A_CONFIRM

async def admin_confirm(update: Update, context: ContextTypes.DEFAULT_TYPE):
    ans = update.message.text.lower()
    from telegram import ReplyKeyboardRemove
    
    if ans == 'ha':
        ud = context.user_data
        if movies_col is not None:
            mid = get_next_movie_id()
            movies_col.insert_one({
                'id': mid,
                'file_id': ud['admin_file_id'],
                'title': ud['admin_title'],
                'year': ud['admin_year'],
                'genre': ud['admin_genre'],
                'type': ud['admin_type'],
                'price': ud['admin_price'],
                'created_at': datetime.now()
            })
            await update.message.reply_text(f"✅ Kino saqlandi! ID: {mid}", reply_markup=ReplyKeyboardRemove())
        else:
            await update.message.reply_text("❌ DB topilmadi, saqlanmadi.", reply_markup=ReplyKeyboardRemove())
    else:
        await update.message.reply_text("❌ Bekor qilindi.", reply_markup=ReplyKeyboardRemove())
        
    context.user_data.clear()
    return ConversationHandler.END

async def cancel_admin(update: Update, context: ContextTypes.DEFAULT_TYPE):
    from telegram import ReplyKeyboardRemove
    context.user_data.clear()
    await update.message.reply_text("Bekor qilindi.", reply_markup=ReplyKeyboardRemove())
    return ConversationHandler.END


if __name__ == '__main__':
    threading.Thread(target=run_flask, daemon=True).start()
    
    app = ApplicationBuilder().token(TOKEN).build()
    
    # Admin Add Movie Conversation Handler
    admin_conv_handler = ConversationHandler(
        entry_points=[MessageHandler(filters.VIDEO & filters.User(ADMIN_ID), admin_video_receive)],
        states={
            A_TITLE: [MessageHandler(filters.TEXT & ~filters.COMMAND, admin_title)],
            A_YEAR: [MessageHandler(filters.TEXT & ~filters.COMMAND, admin_year)],
            A_GENRE: [MessageHandler(filters.TEXT & ~filters.COMMAND, admin_genre)],
            A_TYPE: [MessageHandler(filters.TEXT & ~filters.COMMAND, admin_type)],
            A_PRICE: [MessageHandler(filters.TEXT & ~filters.COMMAND, admin_price)],
            A_CONFIRM: [MessageHandler(filters.TEXT & ~filters.COMMAND, admin_confirm)],
        },
        fallbacks=[CommandHandler('cancel', cancel_admin)]
    )
    
    app.add_handler(CommandHandler("start", start))
    app.add_handler(admin_conv_handler)
    app.add_handler(CallbackQueryHandler(callback_route))
    app.add_handler(PreCheckoutQueryHandler(precheckout_callback))
    app.add_handler(MessageHandler(filters.SUCCESSFUL_PAYMENT, successful_payment_callback))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text))
    
    print("Bot started...")
    app.run_polling()