import pandas as pd
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split

databank = {
    'MonthlyIncome': [3000, 4000, 5000, 6000, 3500, 4500, 5500, 6500, 7000, 8000],
    'CreditScore': [600, 650, 700, 750, 620, 680, 720, 770, 800, 820],
    'LoanApproved': [0, 0, 1, 1, 0, 1, 1, 1, 1, 1]
}

df = pd.DataFrame(databank)

print(df)

X = df[['MonthlyIncome', 'CreditScore']]

y = df['LoanApproved']

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

model = LogisticRegression()
model.fit(X_train, y_train)
new_customer = pd.DataFrame({
    'MonthlyIncome': [6000],
    'CreditScore': [750]
})

prediction = model.predict(new_customer)
probability = model.predict_proba(new_customer)

if prediction[0] == 1:
    print("Loan Approved")
else:
    print("Loan Rejected")

print("Approval Probability:", probability[0][1])